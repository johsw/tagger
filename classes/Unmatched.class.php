<?php

class Unmatched {

  private $unmatched;


  public function __construct($unmatched) {
    $this->unmatched = $unmatched;
  }

  public function logUnmatched() {
    foreach($this->unmatched as $word) {
      if (!empty($word)) {
        if ($this->wordHasBeenLogged($word)) {
          $this->incrementWordCount($word);
        } else {
          $this->logNewWord($word);
        }
      }
    }
  }

  private function wordHasBeenLogged($word) {
    $tagger = Tagger::getTagger();
    $db_conf = $tagger->getConfiguration('db');
    $unmatched_table = $db_conf['unmatched_table'];

    $sql = sprintf("SELECT count FROM $unmatched_table WHERE name = '%s';", $word);
    $result = TaggerQueryManager::query($sql);
    $row = TaggerQueryManager::fetch($result);
    return (bool)$row['count'];
  }
  private function logNewWord($word) {
    $sql = sprintf("INSERT INTO $unmatched_table SET name = '%s', count = 1, created = CURRENT_TIMESTAMP", $word);
    $result = TaggerQueryManager::query($sql);
  }
  private function incrementWordCount($word) {
    $sql = sprintf("UPDATE $unmatched_table SET count = count+1, updated = CURRENT_TIMESTAMP WHERE name ='%s';", $word);
    $result = TaggerQueryManager::query($sql);
  }

}

