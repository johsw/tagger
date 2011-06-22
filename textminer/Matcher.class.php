<?php

abstract class Matcher {
  protected $configuration;

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

  protected function term_query() {
    $tagger_instance = Tagger::getTagger();
    $vocab_names = $tagger_instance->getSetting('vocab_names');
    if (!empty($this->vocabularies) && !empty($this->search_items)) {
      $imploded_words = implode("','", $this->search_items);
      $unmatched = array_flip($this->search_items);

      $result = TaggerQueryManager::getQueryManager($tagger_instance->getConfiguration())->query("SELECT tid, name, vid FROM term_data WHERE vid IN(%s) AND (name IN('%s') OR tid IN(SELECT tid FROM term_synonym WHERE name IN('%s'))) GROUP BY BINARY name", array($this->vocabularies, $imploded_words, $imploded_words));

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