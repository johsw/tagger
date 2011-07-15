<?php

include __ROOT__ . 'logger/TaggerLogManager.class.php';
include __ROOT__ . 'db/TaggerQueryManager.class.php';

abstract class Matcher {
  protected $matches;
  protected $numresults = 0;
  protected $tokens;
  protected $vocabularies;
  protected $nonmatches;
  protected $tagger;

  function __construct($potential_entities, $vocab_id_array) {
    $this->tagger = Tagger::getTagger();

    foreach($potential_entities as $token) {
      $this->tokens[strtolower($token->text)] = $token;
    }
    $this->matches = array();
    $this->nonmatches = array();
    $this->vocabularies = implode(', ', $vocab_id_array);
  }

  protected function term_query() {
    $vocab_names = $this->tagger->getConfiguration('vocab_names');
    if (!empty($this->vocabularies) && !empty($this->tokens)) {
      $imploded_words = implode("','", array_keys($this->tokens));
      $unmatched = array();
      foreach($this->tokens as $token) {
        $unmatched[strtolower($token->text)] = $token;
      }

      // First we find synonyms
      $synonyms = array();
      $query = "SELECT tid, name FROM term_synonym WHERE name IN('$imploded_words') GROUP BY name";
      $result = TaggerQueryManager::query($query);
      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $synonyms[$row['tid']] = $row['name'];
        TaggerLogManager::logVerbose("Synonym:\n" . print_r($row, TRUE));
      }
      $synonym_ids_imploded = implode("','", array_keys($synonyms));

      // Then we find the actual names of entities
      $query = "SELECT COUNT(tid) AS count, tid, name, vid FROM term_data WHERE vid IN($this->vocabularies) AND (name IN('$imploded_words') OR tid IN('$synonym_ids_imploded')) GROUP BY BINARY name";
      TaggerLogManager::logDebug("Match-query:\n" . $query);
      $result = TaggerQueryManager::query($query);
      while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $matchword = '';
        if (array_key_exists(strtolower($row['name']), $unmatched)) {
          unset($unmatched[$row['name']]);
          $matchword = $row['name'];
        }
        elseif(array_key_exists(strtolower($row['tid']), $synonyms)) {
          unset($unmatched[$synonyms[$row['tid']]]);
          $matchword = $synonyms[$row['tid']];
          $this->tokens[strtolower($matchword)]->realName = $row['name'];
        }
        $this->matches[$row['vid']][$row['tid']] = $this->tokens[strtolower($matchword)];
      }
      $this->nonmatches = $unmatched;
    }
    TaggerLogManager::logVerbose("Matches:\n" . print_r($this->matches, true));
  }

  public function get_matches() {
    return $this->matches;
  }
  public function get_nonmatches() {
    return $this->nonmatches;
  }
  abstract protected function match();
}
