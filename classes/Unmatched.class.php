<?php
/**
 * @file
 * Definition of Unmatched.
 */

/**
 * Functionality for logging of Unmatched words.
 */
class Unmatched {

  /**
   * Array of unmatched words.
   */
  private $unmatched;

  /**
   * Constructs an Unmatched object.
   */
  public function __construct($unmatched) {
    $this->unmatched = $unmatched;
  }

  /**
   * Logs all unmatched words.
   *
   * Also increments a counter of how many times it has been logged as
   * unmatched.
   */
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

  /**
   * Checks the database whether the word already is in the unmatched_table.
   */
  private function wordHasBeenLogged($word) {
    $tagger = Tagger::getTagger();
    $db_conf = $tagger->getConfiguration('db');
    $unmatched_table = $db_conf['unmatched_table'];

    $sql = sprintf("SELECT count FROM $unmatched_table WHERE name = '%s';", $word);
    $result = TaggerQueryManager::query($sql);
    $row = TaggerQueryManager::fetch($result);
    return (bool)$row['count'];
  }

  /**
   * Insert a word into the unmatched_table.
   */
  private function logNewWord($word) {
    $sql = sprintf("INSERT INTO $unmatched_table SET name = '%s', count = 1, created = CURRENT_TIMESTAMP", $word);
    $result = TaggerQueryManager::query($sql);
  }

  /**
   * Increments the word's count in the unmatched_table.
   */
  private function incrementWordCount($word) {
    $sql = sprintf("UPDATE $unmatched_table SET count = count+1, updated = CURRENT_TIMESTAMP WHERE name ='%s';", $word);
    $result = TaggerQueryManager::query($sql);
  }

}

