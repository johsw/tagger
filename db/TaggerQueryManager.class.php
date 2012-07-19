<?php
/**
 * @file
 * Contains TaggerQueryManager.
 */

/**
 * The public database interface for Tagger. (Singleton)
 *
 * Will relay database calls/queries through to the QueryHandler which defaults
 * to the TaggerQueryHandler.
 */
class TaggerQueryManager {

  /**
   * Singleton constructor.
   */
  private function __construct() {

  }

  /**
   * Makes a database query.
   *
   * @param string $sql
   *   The SQL query string possibly substitution variables.
   *   Example: @code SELECT tid WHERE name = ':name' @endcode
   *   `IN`-clauses are also supported:
   *   Example: @code SELECT name WHERE vid IN(:vocabularies) @endcode
   * @param array $args
   *   An associative array describing the substitutions to be made.
   *   Example: @code array(':name' => 'Carl'); @endcode
   *   `IN`-clause example:
   *   @code array(':vocabs' => array(13, 15, 17)) @endcode
   *
   * @return $mixed
   *   An SQL query result.
   */
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

  /**
   * Fetches an SQL result.
   *
   * @param mixed $result
   *   An SQL query result from TaggerQueryManager::query()
   * @param string $type
   *   How the data should be returned:
   *   - 'assoc': each row returned as an associative array.
   *   - 'num': each row returned as an integer indexed array.
   *   Defaults to 'assoc'.
   *
   * @return array
   *   An array of associative array or object depending on the $type parameter.
   */
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
   *        for every 100th row (instead of every row).
   *   Defaults to 1000.
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

  /**
   * Quotes a string for insertion into an SQL query.
   *
   * @param string $str
   *   The string to be quoted.
   *
   * @return
   *   The quoted string.
   */
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

