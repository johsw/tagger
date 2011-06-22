<?php

class DrupalDBBuddy implements TaggerQuery {
  
  public static function instance() {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }
    return self::$instance;
  }

  public function query($sql, $args) {
    return db_query($sql, $args);
  }
}
