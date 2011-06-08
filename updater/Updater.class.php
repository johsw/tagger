<?php

/**
*  Updater class
*/


class Updater {

  function __construct($updates) {
    global $conf;
    //print_r($conf);
    $this->parse_updates($updates);
    //print_r($updates); 
    exit;
  }
  
  public function parse_updates($updates) {
    if (is_array($updates)) {
      foreach ($updates as $uri => $set) {
        if (is_array($set)) {
          foreach ($set as $update) {
            $sql = $this->build_sql($update);
          }
        }
      }
    }
  }
  
  public function build_sql($update) {
    
  }
}
