<?php

class TaggerQueryHandler {

  private static $link = NULL;
  private static $instance = NULL;
  private function __construct() {
    $tagger_instance = Tagger::getTagger();
    $db_settings = $tagger_instance->getConfiguration('db');

    try {
      if($db_settings['type'] != 'sqlite') {
        // Anything but SQLite
        $db_settings['dsn'] = $db_settings['type'].':dbname='.$db_settings['name'].';host='.$db_settings['server'];
        $this->link = new PDO($db_settings['dsn'], $db_settings['username'], $db_settings['password'],
                              array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
      } else {
        // SQLite only
        $db_settings['dsn'] = $db_settings['type'] .':'. $db_settings['path'];
        $this->link = new PDO($db_settings['dsn'], '', '',
                              array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
      }
    } catch (PDOException $e) {
      die('Could not connect: ' . $e->getMessage());
    }

    // If you are on an older version of PHP and have trouble with the function
    // call above here, try this instead: mysql_query("SET NAMES 'utf8'");
  }

  public function __destruct() {
    $this->link = NULL;
  }

  public function fetch($result, $type) {
    switch (strtolower($type)) {
      case 'num':
        return $result->fetch(PDO::FETCH_NUM);
        break;

      case 'assoc':
        return $result->fetch(PDO::FETCH_ASSOC);
        break;
      
      default:
        return $result->fetch(PDO::FETCH_ASSOC);
        break;
    }
  }

  public function query($sql, $args) {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }
    
    if (!empty($args)) {
      foreach (array_keys($args) as $key) {
        $value = $args[$key];
        if (is_array($value)) {
          unset($args[$key]);
          $new_keys = array();
          $i = 0;
          foreach ($value as $v) {
            $new_keys[$key . '_' . $i++] = $v;
          }
          # Stolen from Drupal 7.14: includes/database/database.inc:736
          $sql = preg_replace('#' . $key . '\b#', implode(', ', array_keys($new_keys)), $sql);
          $args = array_merge($args, $new_keys);
        }
      }
      $stmt = self::$instance->link->prepare($sql);
      $stmt->execute($args);
      $result = $stmt;
    }
    else {
      $result = self::$instance->link->query($sql);  
    }

    if($result) {
      return $result;
    } else {
      $error_msg = self::$instance->link->errorInfo();
      throw new Exception('Database error: '. $error_msg[2] . "\n" . 'Query: ' . $sql);
    }
  }

  public static function quote($str) {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }

    if (($result = self::$instance->link->quote($str)) === FALSE) {
      throw new Exception('Current database driver does not support quoting.');
    }
    return $result;
  }

  public static function bufferedInsert($table, $fields, $values_array, $num) {
    array_walk($fields, array('self', 'quote'));
    $fields_str = join(',', $fields);

    self::$instance->link->beginTransaction();

    $qmarks_fields = str_repeat("?,", count($fields)-1) . "?";
    $st = self::$instance->link->prepare("INSERT INTO `$table` ($fields_str) VALUES ($qmarks_fields)");

    $insert_count = 0;
    foreach($values_array as $values) {

      if (FALSE === $st->execute($values)) {
        $error_info = $st->errorInfo();
        throw new Exception('Insert failed: ' . $error_info[2]);
      }
      
      $insert_count++;
      if ($insert_count == $num) {
        $commit_bool = self::$instance->link->commit();
        if ($commit_bool === FALSE) {
          throw new Exception('Insert failed: ' . self::$instance->link->errorInfo());
        }
        self::$instance->link->beginTransaction();
      }
    }

    $commit_bool = self::$instance->link->commit();
    if ($commit_bool === FALSE) {
      throw new Exception('Insert failed: ' . self::$instance->link->errorInfo());
    }
  }

}
