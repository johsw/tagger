<?php

require_once 'classes/EntityPreprocessor.class.php';
require_once 'classes/HTMLPreprocessor.class.php';
require_once 'classes/Unmatched.class.php';

class TaggedText {

  private $text;
  private $rating;
  private $ner_vocab_ids;
  private $tag_array;
  private $markedup_text;
  private $use_markup = FALSE;
  private $nl2br = FALSE;
  private $return_uris = FALSE;
  private $return_unmatched = FALSE;
  private $disambiguate = FALSE;
  private $taggerInstance;

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

    $this->taggerInstance = Tagger::getTagger();

    // Change encoding if necessary.
    $this->text = $text;
    if (mb_detect_encoding($this->text) != 'UTF-8') {
      $this->text = utf8_encode($this->text);
    }

    if (!empty($ner_vocab_ids)) {
      $this->ner_vocab_ids = $ner_vocab_ids;
    }
    else {
      $vocab_ids = $this->taggerInstance->getConfiguration('ner_vocab_ids');
      if (!isset($vocab_ids) || empty($vocab_ids)) {
        throw new ErrorException('Missing vocab definition in configuration.');
      }
      $this->ner_vocab_ids = $vocab_ids;
    }
    $this->disambiguate = $disambiguate;
    $this->return_uris = $return_uris;
    $this->return_unmatched = $return_unmatched;
    $this->use_markup = $use_markup;
    $this->nl2br = $nl2br;
  }

  public function process() {

    // Make HTML rating
    if($this->taggerInstance->getConfiguration['HTML_rating']) {
      $HTMLPreprocessor = new HTMLPreprocessor($this->text);
      $HTMLPreprocessor->parse();
      $tokens = $HTMLPreprocessor->tokens;
    }
    else {
      $tokenizer = new Tokenizer(strip_tags($this->text));
      $tokens = $tokenizer->tokens;
    }

    $entityPreprocessor = new EntityPreprocessor($tokens);
    $potentialCandidates = $entityPreprocessor->get_potential_named_entities();
    $potentialCandidates = $this->flattenTokens($potentialCandidates);
    $potentialCandidates = $this->sumRating($potentialCandidates);
    $ner_matcher = new NamedEntityMatcher($potentialCandidates, $this->ner_vocab_ids);
    $ner_matcher->match();
    $this->tag_array = $ner_matcher->get_matches();
    if (FALSE != $this->return_unmatched) {
      $unmatched_words = $ner_matcher->get_nonmatches();
      $unmatched = new Unmatched($unmatched_words);
      $unmatched->logUnmatched();
      if ($this->return_unmatched) {
        //TODO - Process and return unmatched entities
      }
    }
    if ($this->disambiguate) {
      require_once 'classes/Disambiguator.class.php';
      $disambiguator = new Disambiguator($this->tag_array);
      $this->tag_array = $disambiguator->disambiguate();
    }
    if ($this->return_uris) {
      $this->buildUriData();
    }

    $this->markupText();
  }

  private function flattenTokens($tokens) {
    $flattened_tokens = array();
    foreach ($tokens as $token_split) {
      $token_split[0]->text = implode(' ', $token_split);
      $flattened_tokens[] = $token_split[0];
    }
    return $flattened_tokens;
  }

  private function sumRating($tokens) {
    $n = count($tokens)-1;
    $ratedTokens = array();

    for($i = 0; $i < $n; $i++) {
      if(isset($tokens[$i])) {
        $tokens[$i]->rating = (1 + $tokens[$i]->htmlRating) * $tokens[$i]->posRating;
        for($j = $i+1; $j <= $n; $j++) {
          if($tokens[$i]->text == $tokens[$j]->text) {
            $tokens[$i]->rating += (1 + $tokens[$j]->htmlRating) * $tokens[$j]->posRating;
            $tokens[$i]->freqRating++;
            unset($tokens[$j]);
          }
        }
        $rated_tokens[] = $tokens[$i];
      }
    }

    foreach($rated_tokens as $token) {
      $token->rating /= 1 + (($token->freqRating-1) * (1-$this->taggerInstance->getConfiguration('frequency_rating')));
    }

    return $rated_tokens;
  }

  public function getProcessedResponse() {
    $return_arr =  array();
    if ($this->use_markup) {
      $return_arr['markup'] = $this->markedup_text;
    }

    $return_arr['tags'] = $this->tag_array;
    return $return_arr;
  }

  private function markupText() {
    if (!$this->use_markup) {
      return;
    }
    $this->markedup_text = $this->text;
    foreach ($this->tag_array as $terms) {
      foreach ($terms as $tid => $term) {
        $this->markedup_text = str_replace($term['word'], '<span class="tagr-item" id="tid-' . $tid . '" property="dc:subject">' . $term['word'] . '</span> ', $this->markedup_text);
      }
    }
    if ($this->nl2br) {
      $this->markedup_text = nl2br($this->markedup_text);
    }
  }
  private function buildUriData() {
    foreach($this->tag_array as $cat => $tags) {
      foreach($tags as $tid => $tag) {
        $uris = $this->fetchUris($tid);
        $this->tag_array[$cat][$tid]['uris'] = $uris;
      }
    }
  }
  private function fetchUris($tid) {
    $tagger_instance = Tagger::getTagger();
    $sql = sprintf("SELECT dstid, uri FROM linked_data_sources WHERE tid = %s ORDER BY dstid ASC", $tid);
    $result = TaggerQueryManager::query($sql);
    $uris = array();
    $lod_sources = $tagger_instance->getConfiguration('lod_sources');
    while ($row = mysqli_fetch_assoc($result)) {
      $uris[$lod_sources[$row['dstid']]] = $row['uri'];
    }
    return $uris;
  }
}
?>
