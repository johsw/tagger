<?php

/**
*  Updater class
*/


class Updater {

  function __construct() {
    
    $updates = $this->get_latest_updates(); 
    $this->register_updates($updates);

  }
  
  public function get_latest_updates() {
    global $conf;
    $updates = array();
    if (!isset($conf['vocab_updaters']) || empty($conf['vocab_updaters'])) {
      $this->update_error('No defined updaters. Check $conf[\'vocab_updaters\'] in conf.php');
    }
    foreach ($conf['vocab_updaters'] as $url => $options) {
      if ($this->update_minimal_interval_passed($url)) {
        $updates[$url] = fetch_remote_updates($url, $options);
      }
      
    }
    return $updates;
  }
  public function update_minimal_interval_passed($url) {
    $sql = sprintf("SELECT value FROM variables WHERE name='min_interval:". $url ."'", $url);
    $result = DatabaseBuddy::query($sql);
    if (!$result) {
      $this->check_database_table('variables');
    }

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
        $this->update_error('Variables table does not exist and cannon be made.');
      }
    }
  }
  

  public function fetch_remote_updates($url, $options) {
    # code...
  }
  
  public function update_error($error) {
    print "\n";
    print "-------\n";
    print "ERROR: ". $error ."\n";
    print "-------\n";
    exit;
  }
}
