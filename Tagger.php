<?php

class Tagger {

  private static $instance;

  private $conf_settings;
    
  private function __construct()  {
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
    include 'conf.php';
    include 'textminer/DatabaseBuddy.inc.php';
    include 'controllers/TagController.inc.php';
    $this->conf_settings = $tagger_conf;
  }

  public static function getTagger() {
    if (!isset(self::$instance)) {
        $c = __CLASS__;
        self::$instance = new $c;
    }
    return self::$instance;
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
    $controller = new TagController($text, $ner, $disambiguate, $return_uris, $return_unmatched, $use_markup, $nl2br);
    $controller->process();
    return $controller->getProcessedResponse();
  }
}


