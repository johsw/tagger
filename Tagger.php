<?php
require_once 'classes/TaggedText.class.php';

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
    if (isset($this->configuration[$setting])) {
      return $this->configuration[$setting];
    }
    return $this->configuration;
  }

  // Prevent users to clone the instance
  public function __clone() {
    trigger_error('Clone is not allowed.', E_USER_ERROR);
  }



  public function tagText($text, $rating = array(), $ner_vocab_ids = array(), $disambiguate = FALSE, $return_uris = FALSE, $return_unmatched = FALSE, $use_markup = FALSE, $nl2br = FALSE) {
    $controller = new TaggedText($text);
    $controller->process();
    return $controller->getProcessedResponse();
  }
}


