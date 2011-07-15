<?php
require_once __ROOT__ . 'classes/EntityPreprocessor.class.php';
require_once __ROOT__ . 'classes/HTMLPreprocessor.class.php';
require_once __ROOT__ . 'classes/Unmatched.class.php';
require_once __ROOT__ . 'classes/Tag.class.php';

class TaggedText {

  private $text;
  private $ner_vocab_ids;


  private $tokenParts;
  private $token;
  private $tags;
  private $markedupText;
  private $intermediateHTML;
  private $markedupHTML;


  private $findTagsInText = FALSE;
  private $nl2br = FALSE;
  private $return_uris = FALSE;
  private $return_unmatched = FALSE;
  private $disambiguate = FALSE;
  private $tagger;

  /**
   * Constructs a TaggedText object.
   *
   * @param string $text
   *   Text to be tagged.
   * @param array $rating
   *   An associative array containing one more of the keys:
   *     - frequency
   *     - position
   *     - tags
   *   Where each has a value between 0 and 1 to indicate the weighting of each
   *   rating method.
   * @param array $ner_vocab_ids
   *   The database-IDs of the vocabularies to be used.
   */
  public function __construct($text, $rating = array(), $ner_vocab_ids = array(), $disambiguate = FALSE, $return_uris = FALSE, $return_unmatched = FALSE, $use_markup = FALSE, $nl2br = FALSE) {

    if (empty($text)) {
      throw new InvalidArgumentException('No text to find tags in has been supplied.');
    }

    $this->tagger = Tagger::getTagger();

    // Change encoding if necessary.
    $this->text = $text;
    if (mb_detect_encoding($this->text) != 'UTF-8') {
      $this->text = utf8_encode($this->text);
    }

    if (!empty($ner_vocab_ids)) {
      $this->ner_vocab_ids = $ner_vocab_ids;
    }
    else {
      $vocab_ids = $this->tagger->getConfiguration('ner_vocab_ids');
      if (!isset($vocab_ids) || empty($vocab_ids)) {
        throw new ErrorException('Missing vocab definition in configuration.');
      }
      $this->ner_vocab_ids = $vocab_ids;
    }
    $this->disambiguate = $disambiguate;
    $this->return_uris = $return_uris;
    $this->return_unmatched = $return_unmatched;
    $this->findTagsInText = $use_markup;
    $this->nl2br = $nl2br;
  }

  public function process() {
    TaggerLogManager::logVerbose("Text to be tagged:\n" . $this->text);

    // Tokenize - with/without HTML.
    if($this->tagger->getConfiguration('HTML_rating')) {
      $HTMLPreprocessor = new HTMLPreprocessor($this->text, TRUE);
      $HTMLPreprocessor->parse();
      $this->tokenParts = &$HTMLPreprocessor->tokens;
      $this->intermediateHTML = &$HTMLPreprocessor->intermediateHTML;
    }
    else {
      $tokenizer = new Tokenizer(strip_tags($this->text));
      $this->tokenParts = &$tokenizer->tokens;
    }


    // Named entity recognition
    $entityPreprocessor = new EntityPreprocessor($this->tokenParts);
    $potential_entities = $entityPreprocessor->get_potential_named_entities();

    $potential_entities = $this->flattenTokens($potential_entities);

    $this->tokens = $this->rateTokens($potential_entities);
    TaggerLogManager::logDebug("Found potential entities:\n" . print_r($this->tokens, TRUE));

    $this->tags = $this->mergeTokens($this->tokens);
    TaggerLogManager::logDebug("Merged:\n" . print_r($this->tags, TRUE));

    $ner_matcher = new NamedEntityMatcher($this->tags, $this->ner_vocab_ids);
    $ner_matcher->match();
    $this->tags = $ner_matcher->get_matches();


    // Capture unmatched tags
    if (FALSE != $this->return_unmatched) {
      $unmatched_words = $ner_matcher->get_nonmatches();
      $unmatched = new Unmatched($unmatched_words);
      $unmatched->logUnmatched();
      if ($this->return_unmatched) {
        //TODO - Process and return unmatched entities
      }
    }
    // Disambiguate
    if ($this->disambiguate) {
      require_once 'classes/Disambiguator.class.php';
      $disambiguator = new Disambiguator($this->tags);
      $this->tags = $disambiguator->disambiguate();
    }
    if ($this->return_uris) {
      $this->buildUriData();
    }

    TaggerLogManager::logDebug("Marked HTML:\n" . $this->markupText());
    // mark up found tags in HTML
    if($this->findTagsInText) {
      $this->markupText();
    }


  }

  private function flattenTokens($tokens) {
    $flattened_tokens = array();
    foreach ($tokens as $token_split) {
      $token = new Token(implode(' ', $token_split));
      foreach($token_split as $key => $token_part) {
        if($token_part->htmlRating > $token->htmlRating) {
          $token->htmlRating = $token_part->htmlRating;
        }
        if($token_part->posRating > $token->posRating) {
          $token->posRating = $token_part->posRating;
        }
        $token->tokenParts[] = $key;
      }
      $flattened_tokens[] = $token;
    }
    return $flattened_tokens;
  }

  private function rateTokens($tokens) {

    foreach ($tokens as $token) {
        // ATTENTION: this is the rating expression!
      $token->rating = (1 + $token->htmlRating) * $token->posRating;

      foreach ($token->tokenParts as $part_key) {
        $this->tokenParts[$part_key]->rating = $token->rating;
      }
    }
    return $tokens;
  }


  private function mergeTokens($tokens) {
    $tags = array();

    for ($i = 0, $n = count($tokens)-1; $i <= $n; $i++) {
      if (isset($tokens[$i])) {
        $tag = new Tag($tokens[$i]);
        for ($j = $i; $j <= $n; $j++) {
          if (isset($tokens[$j]) && $tag->text == $tokens[$j]->text) {
            $tag->rating += $tokens[$j]->rating;
            $tag->freqRating++;
            $tag->posRating += $tokens[$j]->posRating;
            $tag->htmlRating += $tokens[$j]->htmlRating;
            $tag->tokens[] = $j;
            unset($tokens[$j]);
          }
        }
        $tags[] = $tag;
      }
    }

    foreach ($tags as $tag) {
      $tag->rating /= 1 + (($tag->freqRating-1) * (1-$this->tagger->getConfiguration('frequency_rating')));
    }

    return $tags;
  }

  public function getTags() {
    return $this->tags;
  }

  public function getTextWithTags() {
    return $this->markedupText;
  }

  private function markupText() {

    foreach($this->tags as $tags) {
      foreach ($tags as $tag) {
        foreach ($tag->tokens as $token_key) {
          $token = $this->tokens[$token_key];
          reset($token->tokenParts);
          $start_token_part = &$this->tokenParts[current($token->tokenParts)];
          $end_token_part = &$this->tokenParts[end($token->tokenParts)];

          $start_token_part->text = '<bold>' . $start_token_part->text;
          $end_token_part->text .= '</bold>';
        }
      }
    }

    foreach($this->intermediateHTML as $element) {
      $this->markedupHTML .= $element;
    }

    return $this->markedupHTML;
  }



  private function buildUriData() {
    foreach($this->tags as $cat => $tags) {
      foreach($tags as $tid => $tag) {
        $uris = $this->fetchUris($tid);
        $this->tags[$cat][$tid]['uris'] = $uris;
      }
    }
  }
  private function fetchUris($tid) {
    $sql = sprintf("SELECT dstid, uri FROM linked_data_sources WHERE tid = %s ORDER BY dstid ASC", $tid);
    $result = TaggerQueryManager::query($sql);
    $uris = array();
    $lod_sources = $this->tagger->getConfiguration('lod_sources');
    while ($row = mysqli_fetch_assoc($result)) {
      $uris[$lod_sources[$row['dstid']]] = $row['uri'];
    }
    return $uris;
  }
}
?>
