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
    
    if (!isset($conf['vocab_updater']) || empty($conf['vocab_updater'])) {
      $this->update_error('No defined updaters. Check $conf[\'vocab_updater\'] in conf.php');
    }
    foreach ($conf['vocab_updater'] as $url => $options) {
      if ($this->update_minimal_interval_passed($url)) {
        $updates[$url] = fetch_remote_updates($url, $options);
        # code...
      }
      
    }
    
  }
  public function update_minimal_interval_passed($url) {
    # code...
  }
  
  public function variables_table_exists() {
    $result = DatabaseBuddy::query("SHOW TABLES like 'variables'");
    print $result;
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
