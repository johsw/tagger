<?php

class Tagger {

  private static $instance;

  private $conf_settings;
  
  private $configuration;
    
  private function __construct($configuration)  {
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
    define('TAGGER_DIR', dirname(__FILE__));
    define('TAGGER_DB_CONF', $configuration);
    include 'conf.php';
    include 'db/Query.class.php';
    include 'controllers/TagController.inc.php';
    $this->conf_settings = $tagger_conf;
    $this->configuration = $configuration;
  }

  public static function getTagger($configuration) {
    if (!isset(self::$instance)) {
        $c = __CLASS__;
        self::$instance = new $c;
    }
    return self::$instance;
  }

  public function getConfiguration() {
    return $this->configuration;
  }

  // Prevent users to clone the instance
  public function __clone() {
    trigger_error('Clone is not allowed.', E_USER_ERROR);
  }
  
  public function getSetting($name) {
    if (isset($this->conf_settings[$name])) {
      return $this->conf_settings[$name];
    }
    else {
      // TODO. Make some noise.
    }
  }

  public function tagText($text, $ner, $disambiguate = FALSE, $return_uris = FALSE, $return_unmatched = FALSE, $use_markup = FALSE, $nl2br = FALSE) {
    $controller = new TagController($this->configuration, $text, $ner, $disambiguate, $return_uris, $return_unmatched, $use_markup, $nl2br);
    $controller->process();
    return $controller->getProcessedResponse();
  }
}


