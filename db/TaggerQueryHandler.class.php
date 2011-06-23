<?php

class TaggerQueryHandler {
  
  private static $link = NULL;
  private static $instance = NULL;
  private function __construct() {
    $tagger_instance = Tagger::getTagger();
    $db_settings = $tagger_instance->getConfiguration('db');
    $this->link = mysqli_connect($db_settings['server'], $db_settings['username'], $db_settings['password'], $db_settings['name']);
    if (!$this->link) {
      die('Could not connect: ' . mysqli_error());
    }
    mysqli_set_charset($this->link, 'utf8');
    // If you are on an older version of PHP and have trouble with the function
    // call above here, try this instead: mysql_query("SET NAMES 'utf8'");
  }

  public function __destruct() {
    $this->link = NULL;
  }
  

  public function query($sql, $args) {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }
    return mysqli_query(self::$instance->link, sprintf($sql, $args));
  }
}
