<?php

class TaggerQueryManager {

  private function __construct() {

  }

  public static function query($sql, $args = array()) {

    $tagger_instance = Tagger::getTagger();
    $dbhandler = $tagger_instance->getConfiguration('dbhandler');
    if (!isset($dbhandler) || (isset($dbhandler) && $dbhandler == 'Default')) {
      include_once 'TaggerQueryHandler.class.php';
      return TaggerQueryHandler::query($sql, $args);
    } else {
      if (class_exists($dbhandler.'QueryHandler')) {
        return call_user_func($dbhandler.'QueryHandler::query', $sql, $args);
      }
    }
  }

  public static function fetch($result, $type = 'assoc') {

    $tagger_instance = Tagger::getTagger();
    $dbhandler = $tagger_instance->getConfiguration('dbhandler');
    if (!isset($dbhandler) || (isset($dbhandler) && $dbhandler == 'Default')) {
      include_once 'TaggerQueryHandler.class.php';
      return TaggerQueryHandler::fetch($result, $type);
    } else {
      if (class_exists($dbhandler.'QueryHandler')) {
        return call_user_func($dbhandler.'QueryHandler::fetch', $result, $type);
      }
    }
  }

  /**
   * Does a number ($num) of INSERT statements in one go.
   *
   * @param string $table
   *   Table name
   * @param array $fields
   *   Names of fields/columns to be inserted
   * @param array $values_array
   *   Array of arrays, each array contains the values of 
   *   a row to be inserted
   * @param integer $num
   *   The number of statements to be buffered.
   *   e.g. $num = 100 means that we call the database
   *        for every 100th row (instead of every row)
   *
   */
  public static function bufferedInsert($table, $fields, $values_array, $num = 1000) {

    $tagger_instance = Tagger::getTagger();
    $dbhandler = $tagger_instance->getConfiguration('dbhandler');
    if (!isset($dbhandler) || (isset($dbhandler) && $dbhandler == 'Default')) {
      include_once 'TaggerQueryHandler.class.php';
      return TaggerQueryHandler::bufferedInsert($table, $fields, $values_array, $num);
    }
    else {
      if (class_exists($dbhandler.'QueryHandler')) {
        return call_user_func($dbhandler.'QueryHandler::bufferedInsert', $table, $fields, $values_array, $num);
      }
    }
  }

  public static function quote($str) {

    $tagger_instance = Tagger::getTagger();
    $dbhandler = $tagger_instance->getConfiguration('dbhandler');
    if (!isset($dbhandler) || (isset($dbhandler) && $dbhandler == 'Default')) {
      include_once 'TaggerQueryHandler.class.php';
      return TaggerQueryHandler::quote($str);
    } else {
      if (class_exists($dbhandler.'QueryHandler')) {
        return call_user_func($dbhandler.'QueryHandler::quote', $str);
      }
    }
  }

}

