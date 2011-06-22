<?php

class DefaultQueryHandler implements TaggerQuery {
  private $link = NULL;
  private $instance = NULL;
  private function __construct() {
    $tagger_instance = Tagger::getTagger();
    $db_settings = $tagger_instance->getSetting('db');
    $this->link = mysql_connect($db_settings['server'], $db_settings['username'], $db_settings['password']);
    if (!$this->link) {
      die('Could not connect: ' . mysql_error());
    }
    mysql_set_charset('utf8', $this->link);
    // If you are on an older version of PHP and have trouble with the function
    // call above here, try this instead: mysql_query("SET NAMES 'utf8'");
    mysql_select_db($db_settings['name']);
  }

  public function __destruct() {
    $this->link = NULL;
  }
  
  public static function instance() {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }
    return self::$instance;
  }

  public function taggerQuery($sql, $args) {
    return mysql_query(sprintf($sql, $args), $static_link->link);
  }
}
