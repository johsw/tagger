<?php
require_once 'DatabaseBuddy.inc.php';

abstract class Matcher {
  protected $matches;
  protected $numresults = 0;
  protected $search_items;
  protected $vocabularies;
  protected $nonmatches;

  function __construct($potential_entities, $vocab_array) {
    $search_items = array();
    foreach ($potential_entities as $entity) {
      $search_items[] = implode(' ', $entity);
    }
    $this->search_items = array_unique($search_items);
    $this->matches = array();
    $this->nonmatches = array();
    $this->vocabularies = implode(', ', $vocab_array);
  }

  protected function term_query($word_arr) {
    global $conf;
    $vocab_names = $conf['vocab_names'];
    if (!empty($this->vocabularies) && !empty($word_arr)) {
      $imploded_words = implode("','", $word_arr);
      $unmatched = array_flip($word_arr);

      $sql = sprintf("SELECT l.name AS matchword, c.name AS name, l.vid, l.tid, COUNT(l.tid) AS count FROM lookup AS l JOIN canonical AS c ON c.tid = l.tid  WHERE l.vid IN (%s) AND BINARY l.name IN('%s') GROUP BY BINARY l.name", $this->vocabularies, $imploded_words);
      $result = DatabaseBuddy::query($sql);

      while ($row = mysql_fetch_assoc($result)) {
        if (array_key_exists($row['name'], $unmatched)) {
          unset($unmatched[$row['name']]);
        }
        if (isset($row['synonym']) && array_key_exists($row['synonym'], $unmatched)) {
          unset($unmatched[$row['synonym']]);
        }
        $this->matches[$vocab_names[$row['vid']]][$row['tid']] = array('navn' => $row['name'], 'match' => $row['matchword'], 'hits' => $row['count']);
      }
      $this->nonmatches = array_flip($unmatched);
    }
  }

  public function get_matches() {
    return $this->matches;
  }
  public function get_nonmatches() {
    return $this->nonmatches;
  }
  abstract protected function match();
}