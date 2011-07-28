<?php

/**
*  Retriever class
*/


class Retriever {

  public $updates;

  function __construct() {
    $this->updates = $this->get_latest_updates();
  }

  public function get_latest_updates() {
    global $conf;
    $updates = array();
    if (!isset($conf['vocab_updaters']) || empty($conf['vocab_updaters'])) {
      $this->update_error('No defined updaters. Check $conf[\'vocab_updaters\'] in conf.php', TRUE);
    }
    foreach ($conf['vocab_updaters'] as $url => $options) {
      if ($this->has_update_minimal_interval_passed($url, $options)) {
        $updates[$url] = $this->fetch_remote_updates($url, $options);
      }

    }
    return $updates;
  }

  public function set_variable($name, $value) {
    $sql = sprintf("REPLACE INTO variables SET name='%s', value='%s'", $name, $value);
    return TaggerQueryManager::query($sql);
  }

  public function get_variable($name) {
    $sql = sprintf("SELECT value FROM variables WHERE name='%s'", $name);
    $result = TaggerQueryManager::query($sql);
    if (!$result) {
      $this->check_database_table('variables');
      return TRUE;
    }
    $row = TaggerQueryManager::fetch($result);
    if (!$row) {
      return false;
    }
    return $object['value'];
  }

  public function has_update_minimal_interval_passed($url, $options) {
    $value = $this->get_variable('last_request:'.$url);
    if (!$value) {
      return true;
    }
    return ($value + $options['min_interval']) < time();
  }
  public function set_update_timestamp($url, $options) {

  }

  public function check_database_table($table) {
    $result = DatabaseBuddy::query("SHOW TABLES like 'variables'");
    $object = mysql_fetch_object($result);
    if (!$object) {
      $create = DatabaseBuddy::query("
        CREATE TABLE `variables` (
          `name` varchar(128) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
          `value` longblob NOT NULL,
          UNIQUE KEY `name` (`name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
      ");
      if (!$create) {
        $this->update_error("Variables table doesn't exist and can't be made.");
      }
      return TRUE;
    }
    return TRUE;
  }
  

  public function fetch_remote_updates($url, $options) {
    $starting_point = $this->get_starting_point($url);
    $request = $this->build_request_uri($url, $options, $starting_point);
    $string = @file_get_contents($request);
    if (!$string) {
      $this->update_error('No response from '. $url);
      return false;
    }
    $data = json_decode($string);
    $this->set_update_timestamp($url, $options);
    return $data;
  }
  
  public function build_request_uri($url, $options, $starting_point = 0) {
    $vids = implode('|', $options['vocabs']);
    $params = '/'. $options['items_per_run'] .'/all/'. $vids .'/'. $starting_point;
    return $url.$params; //($no_items = 10, $action = 'all', $vids = 'all', $starting_point = '0')
  }
  
  public function get_starting_point($url) {
    $value = $this->get_variable('starting_point:'.$url);
    if (!$value) {
      return 0;
    }
    //TODO: return 
    return $value;
  }

  public function update_error($error, $fatal = FALSE) {
    print "\n";
    print "-------\n";
    print ($fatal) ? 'FATAL ' : '';
    print "ERROR: ". $error ."\n";
    print "-------\n";
    if ($fatal) {
      exit;
    }
  }
}
