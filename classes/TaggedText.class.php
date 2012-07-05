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

  private $tagger;

  /**
   * Constructs a TaggedText object.
   *
   * @param string $text
   *   Text to be tagged.
   */
  public function __construct($text) {

    if (empty($text)) {
      throw new InvalidArgumentException('No text to find tags in has been supplied.');
    }


    // Change encoding if necessary.
    $this->text = $text;
    if (mb_detect_encoding($this->text) != 'UTF-8') {
      $this->text = utf8_encode($this->text);
    }

    $this->tagger = Tagger::getTagger();

  }

  public function process() {
    TaggerLogManager::logVerbose("Text to be tagged:\n" . $this->text);

    // Tokenize - with/without HTML.
    if (Tagger::getConfiguration('named_entity', 'rating', 'HTML') !== 0) {
      require_once __ROOT__ . 'classes/HTMLPreprocessor.class.php';
      $preprocessor = new HTMLPreprocessor($this->text);
    }
    else {
      require_once __ROOT__ . 'classes/PlainTextPreprocessor.class.php';
      $preprocessor = new PlainTextPreprocessor($this->text);
    }
    $preprocessor->parse();
    $this->partialTokens = &$preprocessor->tokens;
    $this->paragraphCount = $preprocessor->paragraphCount;
    $this->tokenCount = $preprocessor->tokenCount;
    $this->intermediateHTML = $preprocessor->intermediateHTML;

    // Rate the partial tokens
    foreach ($this->partialTokens as $token) {
      $token->rateToken($this->tokenCount, $this->paragraphCount, Tagger::getConfiguration('named_entity', 'rating'));
    }
    TaggerLogManager::logDebug("Tokens\n" . print_r($this->partialTokens, TRUE));

    // Keyword extraction
    // - if keyword-vocabs are provided
    if (count( Tagger::getConfiguration('keyword', 'vocab_ids') ) > 0) {
      require_once __ROOT__ . 'classes/KeywordExtractor.class.php';
      // Deep copy of partialTokens
      $keyword_extractor = new KeywordExtractor(unserialize(serialize($this->partialTokens)));
      $keyword_extractor->determine_keywords();
      if (isset($keyword_extractor->tags) && !empty($keyword_extractor->tags)) {
        $this->tags += $keyword_extractor->tags;
      }
    }

    // NER
    // - if NER-vocabs are provided
    if (count( Tagger::getConfiguration('named_entity', 'vocab_ids') ) > 0) {
      // Do named entity recognition: find named entities.
      $ner_matcher = new NamedEntityMatcher($this->partialTokens);
      $ner_matcher->match();
      $tags = $ner_matcher->get_matches();

      // Rate the tags (named entities).
      array_walk_recursive($tags, create_function('$tag', '$tag->rate();'));


      // Capture unmatched tags
      if (Tagger::getConfiguration('named_entity', 'log_unmatched')) {
        $unmatched_entities = $ner_matcher->get_nonmatches();
        $unmatched = new Unmatched($unmatched_entities);
        $unmatched->logUnmatched();
      }
      // Disambiguate
      if (Tagger::getConfiguration('named_entity', 'disambiguate')) {
        require_once 'classes/Disambiguator.class.php';
        $disambiguator = new Disambiguator($tags, $this->text);
        $tags = $disambiguator->disambiguate();
      }
      $this->tags += $tags;

      // mark up found tags in HTML
      if (Tagger::getConfiguration('named_entity', 'highlight', 'enable')) {
        $this->highlightTags();
        TaggerLogManager::logDebug("HTML with highlighted tags:\n" . $this->highlightedText);
      }
    }

    // Linked data
    if (Tagger::getConfiguration('linked_data')) {
      $this->tags = $this->addUris($this->tags);
    }
  }

  public function getTags($options = array()) {

    $default = Tagger::getConfiguration();

    // let $options array override $configuration temporarily
    Tagger::setConfiguration($default, $options);

    if ( Tagger::getConfiguration('return_full_tag_object') ) {
      return $this->tags;
    }

    $tags = array();

    foreach (array('keyword', 'named_entity') as $type) {
      $vocab_ids = Tagger::getConfiguration($type, 'vocab_ids');
      if ( Tagger::getConfiguration($type, 'debug') ) {
        return array_intersect_key($this->tags, array_flip($vocab_ids));
      }
      else {
        $type_tags = array();
        $public_fields = Tagger::getConfiguration($type, 'public_fields');
        foreach ($vocab_ids as $name => $id) {
          if ( isset($this->tags[$id]) ) {
            $type_tags[$name] = array();
            foreach ($this->tags[$id] as $key => $tag) {
              $type_tags[$name][$key] = array();
              foreach ($public_fields as $field => $public_name) {
                // get_object_vars($tag);
                if (isset($tag->$field)) {
                  $type_tags[$name][$key][$public_name] = $tag->$field;
                }
              }
            }
            uasort($type_tags[$name], create_function('$a, $b', 'return strnatcmp($b["rating"], $a["rating"]);'));
          }
        }
        $tags += $type_tags;
      }
    }

    //$tags = create_output('keyword', $this->tags) + create_output('named_entity', $this->tags);

    Tagger::setConfiguration($default);

    return $tags;
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

              $tag_start = Tagger::getConfiguration('named_entity', 'highlight', 'start_tag');
              if (Tagger::getConfiguration('named_entity', 'highlight', 'substitution')) {
                $tag_start = str_replace("!!ID!!", array_search($start_token_part, $token->tokenParts), $tag_start);
              }

              $start_token_part->text = $tag_start . $start_token_part->text;
              $end_token_part->text .= Tagger::getConfiguration('named_entity', 'highlight', 'end_tag');

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

  private function addUris($tags) {
    if (empty($tags)) {
      return $tags;
    }

    $linked_data_table = Tagger::getConfiguration('db', 'linked_data_table');

    $tids = array();
    foreach ($tags as $vid => $vid_tags) {
      foreach ($vid_tags as $tid => $tag) {
        $tids[] = $tid;
      }
    }
    $tids_list = implode(',', $tids);

    $sql = sprintf("SELECT tid, dstid, uri FROM $linked_data_table WHERE tid IN(%s) ORDER BY dstid ASC", $tids_list);
    $result = TaggerQueryManager::query($sql);
    $uris = array();
    $lod_sources = Tagger::getConfiguration('named_entity', 'lod_sources');
    while ($row = TaggerQueryManager::fetch($result)) {
      if (!isset($uris[$row['tid']])) {
        $uris[$row['tid']] = array();
      }
      $uris[$row['tid']][$lod_sources['id' . $row['dstid']]] = $row['uri'];
    }

    foreach ($tags as $cat => $cat_tags) {
      foreach ($cat_tags as $tid => $tag) {
        if (isset($uris[$tid])) {
          $tags[$cat][$tid]->linked_data = $uris[$tid];
        }
      }
    }

    return $tags;
  }

}

