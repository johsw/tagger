<?php
ini_set('memory_limit', '92M');

define('__ROOT__', dirname(__FILE__) . '/');
define('TAGGER_VERSION', 4);
mb_internal_encoding('UTF-8');
require_once __ROOT__ . 'classes/TaggerHelpers.class.php';
require_once __ROOT__ . 'classes/TaggedText.class.php';
require_once __ROOT__ . 'logger/TaggerLogManager.class.php';

class Tagger {

  private static $instance;

  private static $conf_settings;

  private static $configuration;

  public static $initwords;
  public static $prefix_infix;
  public static $stopwords;

  private static $override = array('vocab_ids');

  private function __construct($configuration = array(), $file = 'conf.php')  {
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
    define('TAGGER_DIR', dirname(__FILE__));
    include 'defaults.php';
    $tagger_conf = array_merge($tagger_conf, $configuration);
    if (!isset($configuration) || empty($configuration)) {
      if(file_exists(__ROOT__ . $file)) {
        include $file;
      }
      else {
        throw new Exception("Configuration file '$file' not found.", 1);
      }
    }
    self::$configuration = $tagger_conf;


    $wordlists = array('initwords', 'prefix_infix', 'stopwords');
    foreach ($wordlists AS $wordlist) {
      if (self::$$wordlist == NULL) {
        $path = realpath(__ROOT__ .'resources/'. $wordlist .'/'. $wordlist .'_'. 
          self::$configuration['language'] .'.txt');
        self::$$wordlist = array_flip(file($path, FILE_IGNORE_NEW_LINES));
      }
    }

  }

  public function getTaggerVersion() {
    return TAGGER_VERSION;
  }
  
  public function getVocabularyIds() {
    $sql = sprintf("SELECT vid FROM tagger_lookup GROUP BY vid");
    $result = TaggerQueryManager::query($sql);
    $ids = array();
    while ($row = TaggerQueryManager::fetch($result)) {
      $ids[$row['vid']] = $row['vid'];
    }
    return $ids;
  }


  public static function getTagger($configuration = array(), $file = 'conf.php') {
    if (!isset(self::$instance)) {
        $c = __CLASS__;
        self::$instance = new $c($configuration, $file);
    }
    return self::$instance;
  }


  public static function getConfiguration() {
    self::getTagger();

    $arg_count = func_num_args();
    if ($arg_count = 0) {
      return self::$configuration;
    }
    else {
      $opt = self::$configuration;
      $setting_str = '$configuration';
      foreach(func_get_args() as $arg) {
        $setting_str .= "['$arg']";
        if (isset($opt[$arg])) {
          $opt = $opt[$arg];
        } else {
          throw new ErrorException('Setting ' . $setting_str . ' not found in configuration.');
        }
      }
      return $opt;
    }
  }

  public static function setConfiguration() {
    $arg_count = func_num_args();
    $args = func_get_args();

    // if all arguments are arrays - merge them
    if ( !in_array(FALSE, array_map('is_array', $args), TRUE) ) {
      self::$configuration = call_user_func_array(
        array('TaggerHelpers', 'arrayMergeRecursiveOverride'),
        array_merge(array(self::$override, self::$configuration), $args)
      );
      return self::$configuration;
    }

    // if all arguments are strings - set the option specified
    if ( !in_array(FALSE, array_map('is_string', $args), TRUE) ) {
      if ($arg_count < 2) {
        throw new ErrorException('Need at least two arguments.');
      }

      $opt =& self::$configuration;
      $l = array_slice(func_get_args(), 1);
      $setting_str = '$configuration';
      foreach($l as $arg) {
        $setting_str .= "['$arg']";
        if (is_array($opt) && isset($opt[$arg])) {
          $opt =& $opt[$arg];
        } else {
          throw new ErrorException('Setting ' . $setting_str . ' not found in configuration.');
        }
      }
      $opt = func_get_arg(0);
      return $opt;
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
   *   TaggedText object
   */

  public function tagText($text, $options = array()) {

    $default = self::$configuration;

    // let $options array override $configuration temporarily
    self::setConfiguration(self::$configuration, $options);

    //if (empty($options['ner_vocab_ids']) && empty($options['keyword_vocab_ids'])) {
    //  throw new ErrorException('Missing vocab definition in configuration.');
    //}

    $tagged_text = new TaggedText($text);
    $tagged_text->process();

    self::$configuration = $default;

    return $tagged_text;
  }

  /**
   * Log to the internal Tagger log
   *
   * @param $message
   *   The text to be logged
   * @param $level
   *   The logging level of the message ('Verbose', 'Warning', 'Standard')
   */
  public function log($message, $level = 'Standard') {
    $level = array_search($level, TaggerLogManager::$LOG_TYPE);
    if ($level === FALSE) {
      $level = TaggerLogManager::STANDARD;
    }
    TaggerLogManager::logMsg($message, $level);
  }

}
