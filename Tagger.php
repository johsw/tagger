<?php
define('__ROOT__', dirname(__FILE__) . '/');
mb_internal_encoding('UTF-8');
require_once __ROOT__ . 'classes/TaggedText.class.php';

class Tagger {

  private static $instance;

  private $conf_settings;

  private $configuration;


  private function __construct($configuration = array())  {
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
    define('TAGGER_DIR', dirname(__FILE__));
    include 'defaults.php';
    $tagger_conf = array_merge($tagger_conf, $configuration);
    if (!isset($configuration) || empty($configuration)) {
      include 'conf.php';
    }
    $this->configuration = $tagger_conf;

  }

  public static function getTagger($configuration = array()) {
    if (!isset(self::$instance)) {
        $c = __CLASS__;
        self::$instance = new $c($configuration);
    }
    return self::$instance;
  }

  public function getConfiguration($setting = NULL) {
    if ($setting === NULL) {
      return $this->configuration;
    }
    else if (isset($this->configuration[$setting])) {
      return $this->configuration[$setting];
    }
    else {
      return FALSE;
    }
  }

  public function setConfiguration($setting, $value) {
    if (isset($this->configuration[$setting])) {
      $this->configuration[$setting] = $value;
      return $this->configuration[$setting];
    }
    else {
      throw new ErrorException('Setting ' . $setting . ' not found in configuration.');
    }
  }

  // Prevent users to clone the instance
  public function __clone() {
    trigger_error('Clone is not allowed.', E_USER_ERROR);
  }

  /**
   * This is the main function to call, when you use Tagger.
   *
   * @param $text
   *   The text you want to tag.
   * @param array $options
   *   An associative array of additional options, with the following elements:
   *   - 'ner_vocab_ids': An numeric array vocabularies you want to use for
   *     NER (named entity recognition). Keys are vocabulary ids. Values are
   *     vocabulary names.
   *   - 'keyword_vocab_ids': An numeric array vocabularies you want to use for
   *     Keyword Extraction´. Keys are vocabulary ids. Values are vocabulary names.
   *   - 'rate_html': Boolean indication wheter html-tags should be used to rate
   *     relevancy.
   *   - 'return_marked_text': Boolean, indicates whether Tagger should return
   *     text with markup.
   *   - 'rating': An array TODO: explain array
   *   - 'disambiguate': Boolean indicating whether Tagger should try to disambiguate
   *     ambigous tags.
   *   - 'return_uris': Boolean indicating wheter Tagger should return URI's for
   *     each tag
   *   - 'log_unmatched': Boolean indicating whether unmatched potential 
   *     NER candidates should be logged
   *   - 'nl2br': Boolean indicating whether newlines should be convertet to br-tags
   * @return
   *   An HTML string containing a link to the given path.
   */

  public function tagText($text, $options) {
    if (empty($options)) {
      $options = array();
    }
    if (empty($options['ner_vocab_ids']) || !isset($options['ner_vocab_ids'])) {
      $options['ner_vocab_ids'] = $this->getConfiguration('ner_vocab_ids');
    }
    if (empty($options['keyword_vocab_ids']) || !isset($options['keyword_vocab_ids'])) {
      $options['keyword_vocab_ids'] = $this->getConfiguration('keyword_vocab_ids');
    }

    if (empty($options['ner_vocab_ids']) && empty($options['keyword_vocab_ids'])) {
      throw new ErrorException('Missing vocab definition in configuration.');
    }
    if (empty($options['rating'])) {
      $options['rating']['frequency'] = $this->getConfiguration('frequency_rating');
      $options['rating']['positional'] = $this->getConfiguration('positional_rating');
      $options['rating']['HTML'] = $this->getConfiguration('HTML_rating');

      $options['rating']['positional_minimum'] = $this->getConfiguration('positional_minimum_rating');
      $options['rating']['positional_critical_token_count'] = $this->getConfiguration('positional_critical_token_count_rating');


      if ($key = array_search(FALSE, $options['rating'], TRUE)) {
        throw new ErrorException('Missing ' . $key . '_rating definition in configuration.');
      }
    }

    $tagged_text = new TaggedText($text, $options);
    $tagged_text->process();
    return $tagged_text;
  }
}


