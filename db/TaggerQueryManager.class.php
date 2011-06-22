<?php

class TaggerQueryManager {

  private function __construct() {

  }

  public static function query($sql, $args = array()) {
    if (!isset(self::$manager)) {
      $tagger_instance = Tagger::getTagger();
      $dbhandler = $tagger_instance->getConfiguration('dbhandler');
      
      if (!isset($dbhandler) || (isset($dbhandler) && $dbhandler == 'default')) {
        include 'DefaultQueryHandler.class.php';
        return DefaultQueryHandler::query($sql, $args);
      } else {
        if (class_exists($dbhandler.'QueryHandler')) {
          return call_user_func($dbhandler.'QueryHandler::query', $sql, $args);
        }
      }
    }
  }
}