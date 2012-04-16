<?php

require_once __ROOT__ . 'logger/TaggerLogManager.class.php';

require_once __ROOT__ . 'classes/NamedEntityMatcher.class.php';
require_once __ROOT__ . 'classes/Unmatched.class.php';
require_once __ROOT__ . 'classes/Tag.class.php';

class TaggedText {

  private $text;

  private $words;

  private $tokenParts;
  private $token;
  private $tags = array();
  private $tokenCount;
  private $paragraphCount;

  private $highlightedText;
  private $intermediateHTML;

  private $options = array();
  private $tagger;

  /**
   * Constructs a TaggedText object.
   *
   * @param string $text
   *   Text to be tagged.
   * @param array $options
   *   See documentation of tagText funtion
   */
  public function __construct($text, $options) {

    if (empty($text)) {
      throw new InvalidArgumentException('No text to find tags in has been supplied.');
    }


    // Change encoding if necessary.
    $this->text = $text;
    if (mb_detect_encoding($this->text) != 'UTF-8') {
      $this->text = utf8_encode($this->text);
    }

    $this->tagger = Tagger::getTagger();
    $this->options = $options;

  }

  public function process() {
    TaggerLogManager::logVerbose("Text to be tagged:\n" . $this->text);

    // Tokenize - with/without HTML.
    if ($this->options['named_entity']['rating']['HTML'] !== 0) {
      require_once __ROOT__ . 'classes/HTMLPreprocessor.class.php';
      $preprocessor = new HTMLPreprocessor($this->text, $this->options);
    }
    else {
      require_once __ROOT__ . 'classes/PlainTextPreprocessor.class.php';
      $preprocessor = new PlainTextPreprocessor($this->text, $this->options);
    }
    $preprocessor->parse();
    $this->partialTokens = &$preprocessor->tokens;
    $this->paragraphCount = $preprocessor->paragraphCount;
    $this->tokenCount = $preprocessor->tokenCount;
    $this->intermediateHTML = $preprocessor->intermediateHTML;

    // Rate the partial tokens
    foreach ($this->partialTokens as $token) {
      $token->rateToken($this->tokenCount, $this->paragraphCount, $this->options['named_entity']['rating']);
    }
    TaggerLogManager::logDebug("Tokens\n" . print_r($this->partialTokens, TRUE));

    // Keyword extraction
    // - if keyword-vocabs are provided
    if (count($this->options['keyword_vocab_ids']) > 0) {
      require_once __ROOT__ . 'classes/KeywordExtractor.class.php';
      // Deep copy of partialTokens
      $keyword_extractor = new KeywordExtractor(unserialize(serialize($this->partialTokens)), $this->options);
      $keyword_extractor->determine_keywords();
      if (isset($keyword_extractor->tags) && !empty($keyword_extractor->tags)) {
        $this->tags += $keyword_extractor->tags;
      }
    }

    // Do NER if NER-vocabs are provided
    if (count($this->options['ner_vocab_ids']) > 0) {

      // Do named entity recognition: find named entities.
      $ner_matcher = new NamedEntityMatcher($this->partialTokens);
      $ner_matcher->match();
      $tags = $ner_matcher->get_matches();

      // Rate the tags (named entities).
      $rating = $this->options['named_entity']['rating'];
      //array_walk_recursive($tags, call_user_func(array('Tag', 'rate'), $rating));
      array_walk_recursive($tags, create_function('$tag', '$tag->rate();'));


      // Capture unmatched tags
      if ($this->options['log_unmatched']) {
        $unmatched_entities = $ner_matcher->get_nonmatches();
        $unmatched = new Unmatched($unmatched_entities);
        $unmatched->logUnmatched();
      }
      // Disambiguate
      if ($this->options['disambiguate']) {
        require_once 'classes/Disambiguator.class.php';
        $disambiguator = new Disambiguator($tags, $this->text);
        $tags = $disambiguator->disambiguate();
      }
      if ($this->options['return_uris']) {
        $this->buildUriData();
      }
      $this->tags += $tags;

      // mark up found tags in HTML
      if ($this->options['highlight']['enable']) {
        $this->highlightTags();
        TaggerLogManager::logDebug("HTML with highlighted tags:\n" . $this->highlightedText);
      }
    }
  }

  public function getTags() {
    return $this->tags;
  }

  public function getHighlightedText() {
    return $this->highlightedText;
  }

  private function highlightTags() {

    $this->highlightedText = '';

    $tags = $this->tags;
    unset($tags[16]);

    foreach ($tags as $category_tags) {
      foreach ($category_tags as $tag) {
        foreach ($tag->tokens as $synonym_tokens) {
          foreach ($synonym_tokens as $token) {
            if (!$token->hasBeenHighlighted) {
              reset($token->tokenParts);
              $start_token_part = &current($token->tokenParts);
              $end_token_part = &end($token->tokenParts);

              $tag_start = $this->options['highlight']['start_tag'];
              if ($this->options['highlight']['substitution']) {
                $tag_start = str_replace("!!ID!!", array_search($start_token_part, $token->tokenParts), $tag_start);
              }

              $start_token_part->text = $tag_start . $start_token_part->text;
              $end_token_part->text .= $this->options['highlight']['end_tag'];

              $token->hasBeenHighlighted = TRUE;
            }
          }
        }
      }
    }

    foreach ($this->intermediateHTML as $element) {
      $this->highlightedText .= $element;
    }

    return $this->highlightedText;
  }



  private function buildUriData() {
    foreach ($this->tags as $cat => $tags) {
      foreach ($tags as $tid => $tag) {
        $uris = $this->fetchUris($tid);
        $this->tags[$cat][$tid]->uris = $uris;
      }
    }
  }
  private function fetchUris($tid) {
    $db_conf = $this->tagger->getConfiguration('db');
    $linked_data_table = $db_conf['linked_data_table'];

    $sql = sprintf("SELECT dstid, uri FROM $linked_data_table WHERE tid = %s ORDER BY dstid ASC", $tid);
    $result = TaggerQueryManager::query($sql);
    $uris = array();
    $lod_sources = $this->tagger->getConfiguration('lod_sources');
    while ($row = TaggerQueryManager::fetch($result)) {
      $uris[$lod_sources[$row['dstid']]] = $row['uri'];
    }
    return $uris;
  }


}
?>
