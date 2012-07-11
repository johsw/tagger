<?php

require_once __ROOT__ . 'logger/TaggerLogManager.class.php';
require_once __ROOT__ . 'db/TaggerQueryManager.class.php';

abstract class Matcher {
  protected $matches;
  protected $numresults = 0;
  private $tokens;
  protected $vocabularies;
  protected $nonmatches;

  function __construct($tokens) {
    $this->setTokens($tokens);

    $this->matches = array();
    $this->nonmatches = array();
    $this->vocabularies = Tagger::getConfiguration('named_entity', 'vocab_ids');
  }

  protected function term_query() {
    $lookup_table = Tagger::getConfiguration('db', 'lookup_table');

    if (!empty($this->vocabularies) && !empty($this->tokens)) {
      $unmatched = $this->tokens;

      // First we find synonyms
      $query = "SELECT tid, name FROM $lookup_table WHERE name IN(:words) AND vid IN(:vocabularies)";
      $args = array(':words' => array_keys($this->tokens), ':vocabularies' => array_values($this->vocabularies));
      $result = TaggerQueryManager::query($query, $args);

      $synonyms = array();
      while ($row = TaggerQueryManager::fetch($result)) {
        $synonyms[$row['tid']][] = mb_strtolower($row['name']);
        unset($unmatched[mb_strtolower($row['name'])]);
        TaggerLogManager::logDebug("Synonym:\n" . print_r($row, TRUE));
      }
      // Then we find the actual names of entities
      if (!empty($synonyms)) {

        $query = "SELECT COUNT(tid) AS count, tid, name, vid, GROUP_CONCAT(tid) AS tids FROM $lookup_table WHERE vid IN(:vocabularies) AND tid IN(:synonym_ids) AND canonical = 1 GROUP BY name";
        $args = array(':vocabularies' => array_values($this->vocabularies), ':synonym_ids' => array_keys($synonyms));
        $result = TaggerQueryManager::query($query, $args);

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
            $match = TagProcessor::mergeTags($row_matches);
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
        TaggerLogManager::logVerbose("Matches:\n" . print_r($this->matches, TRUE));
      }
      $this->nonmatches = $unmatched;
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

  protected function setTokens($tokens) {
    foreach($tokens as $token) {
      $this->tokens[mb_strtolower($token->text)] = $token;
    }
  }

  public function __set($name, $value) {
    if($name == 'tokens') {
      throw new Exception('Cannot set $tokens, use setTokens() instead.', 1);
    }
  }

}
