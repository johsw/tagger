<?php
/**
 * @file
 * Definition of TagProcessor.
 */

require_once __ROOT__ . 'logger/TaggerLogManager.class.php';

require_once __ROOT__ . 'classes/NamedEntityMatcher.class.php';
require_once __ROOT__ . 'classes/Unmatched.class.php';
require_once __ROOT__ . 'classes/Tag.class.php';

/**
 * Main processor for Tagger.
 *
 * The class returned from Tagger::tagText().
 * Contains tags and the highlighted text if requested.
 */
class TagProcessor {

  /**
   * The text to be tagged.
   */
  private $text;

  /**
   * Tokens returned from Tokenizer.
   */
  private $partialTokens;

  /**
   * Number of tokens.
   */
  private $tokenCount;

  /**
   * Number of paragraphs found by preprocessor. (HTML or plaintext)
   */
  private $paragraphCount;

  /**
   * Structure for highlighting tags.
   */
  private $intermediateHTML;

  /**
   * Tags returned from Matcher.
   */
  private $tags = array();

  /**
   * Text with tags highlighted by HTML.
   */
  private $highlightedText;


  /**
   * Constructs a TagProcessor object.
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

  }

  /**
   * Finds tags in the text.
   *
   * The main function which:
   * - Preprocesses the text: finds paragraphs and tokens.
   *   @see PlainTextPreprocessor
   *   @see HTMLPreprocessor
   * - Extracts keywords from the text (if enabled)
   *   @see KeywordExtractor
   *
   * @param string $text
   *   Text to be tagged.
   */
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

  /**
   * Merge identical tokens into tags.
   *
   * Tokens with same text are merged into a single tag.
   *
   * @param array $tokens
   *   The tokens to be merged.
   *
   * @return array $tags
   *   The tags of the text.
   */
  public static function mergeTokens($tokens) {
    $tags = array();

    for ($i = 0, $n = count($tokens)-1; $i <= $n; $i++) {
      if (isset($tokens[$i])) {
        $tag = new Tag($tokens[$i]);
        for ($j = $i; $j <= $n; $j++) {
          if (isset($tokens[$j]) && $tag->text == $tokens[$j]->text) {
            $tag->tokens[$tag->text][] = &$tokens[$j];
            unset($tokens[$j]);
          }
        }
        $tags[] = $tag;
      }
    }

    return $tags;
  }

  /**
   * Merges tags.
   *
   * Relevant when synonyms are present in the text.
   * Tags relating to the same entity but with different names/texts are merged
   * as synonyms of a new tag.
   *
   * @param array $tags
   *   The tags to be merged
   * @param string $real_name
   *   The canonical name of the tags
   *
   * @return Tag
   *   The merged tag
   */
  public static function mergeTags($tags, $real_name = '') {
    $ret_tag = new Tag($real_name);
    $ret_tag->realName = $real_name;
    foreach ($tags as $tag) {
      $ret_tag->synonyms = array_unique(array_merge($ret_tag->synonyms, $tag->synonyms));
      $ret_tag->tokens   = array_merge_recursive($ret_tag->tokens, $tag->tokens);
      if(isset($tag->ambiguous)) {
        $ret_tag->ambiguous = $tag->ambiguous;
      }
      if(isset($tag->meanings)) {
        $ret_tag->meanings = $tag->meanings;
      }
    }
    return $ret_tag;
  }

  /**
   * Return the found tags as an associative array (default) or as Tag objects.
   *
   * @param array $options
   *   Options to be overridden. Defaults to array().
   *
   * @return array $tags
   *   Either an associative array (default) or an array of Tag objects.
   */
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

  /**
   * Get text with tags highlighted.
   *
   * @return string $this->highlightedText
   *   The input text with found tags highlighted.
   */
  public function getHighlightedText() {
    return $this->highlightedText;
  }

  /**
   * Highlights found tags in the text.
   */
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
  }

  /**
   * Finds and adds linked data (URIs) to found tags.
   *
   * @param array $tags
   *
   * @return array $tags
   *   Tags with linked data
   */
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

