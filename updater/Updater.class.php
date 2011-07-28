<?php

/**
*  Updater class
*/


class Updater {

  function __construct($updates) {
    global $conf;
    $this->tagger = Tagger::getTagger();
    $this->parse_updates($updates);
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
