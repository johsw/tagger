<?php

require_once __ROOT__ . 'logger/TaggerLogManager.class.php';
require_once __ROOT__ . 'db/TaggerQueryManager.class.php';

abstract class Matcher {
  protected $matches;
  protected $numresults = 0;
  protected $tokens;
  protected $vocabularies;
  protected $nonmatches;
  protected $tagger;

  function __construct($tokens, $vocab_id_array) {
    $this->tagger = Tagger::getTagger();

    foreach($tokens as $token) {
      $this->tokens[addslashes(mb_strtolower($token->text))] = $token;
    }
    $this->matches = array();
    $this->nonmatches = array();
    $this->vocabularies = implode(', ', $vocab_id_array);
  }

  protected function term_query() {

    if (!empty($this->vocabularies) && !empty($this->tokens)) {
      $imploded_words = implode("','", array_keys($this->tokens));
      $unmatched = $this->tokens;

      // First we find synonyms
      $synonyms = array();
      $query = "SELECT tid, name FROM term_synonym WHERE name IN('$imploded_words') GROUP BY name";
      $result = TaggerQueryManager::query($query);
      while ($row = TaggerQueryManager::fetch($result)) {
        $synonyms[$row['tid']][] = mb_strtolower($row['name']);
        unset($unmatched[mb_strtolower($row['name'])]);
        TaggerLogManager::logDebug("Synonym:\n" . print_r($row, TRUE));
      }
      $synonym_ids_imploded = implode("','", array_keys($synonyms));
      $imploded_words = implode("','", array_keys($unmatched));

      // Then we find the actual names of entities
      $query = "SELECT COUNT(tid) AS count, tid, name, vid, GROUP_CONCAT(tid) AS tids FROM term_data WHERE vid IN($this->vocabularies) AND (name IN('$imploded_words') OR tid IN('$synonym_ids_imploded')) GROUP BY name";
      TaggerLogManager::logDebug("Match-query:\n" . $query);
      $result = TaggerQueryManager::query($query);

      while ($row = TaggerQueryManager::fetch($result)) {
        if($row['name'] != '') {
          $row_matches = array();
          $row_name_lowered = mb_strtolower($row['name']);
          if (array_key_exists($row_name_lowered, $unmatched)) {
            unset($unmatched[$row_name_lowered]);
            $row_matches[] = $this->tokens[$row_name_lowered];
          }
          if (array_key_exists($row['tid'], $synonyms)) {
            foreach ($synonyms[$row['tid']] as $synonym) {
              unset($unmatched[$synonym]);
              $row_matches[] = $this->tokens[$synonym];
            }
          }
          $match = Tag::mergeTags($row_matches);
          $match->ambiguous = ($row['count'] > 1);
          if ($match->ambiguous) {
            $match->meanings = $row['tids'];
          }
          if (isset($this->matches[$row['vid']][$row['tid']])) {
            $this->matches[$row['vid']][$row['tid']] = Tag::mergeTags(array($match, $this->matches[$row['vid']][$row['tid']]), $row['name']);
          } else {
            $match->realName = $row['name'];
            $this->matches[$row['vid']][$row['tid']] = $match;
          }
        }
      }
      $this->nonmatches = $unmatched;
      TaggerLogManager::logVerbose("Matches:\n" . print_r($this->matches, TRUE));
      TaggerLogManager::logVerbose("Unmatched:\n" . print_r($this->nonmatches, TRUE));
    }
    else if(empty($this->vocabularies)) {
      throw new ErrorException('No vocabularies given to Matcher.');
    }
    else if(empty($this->tokens)) {
      throw new ErrorException('No tokens given to Matcher.');
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
