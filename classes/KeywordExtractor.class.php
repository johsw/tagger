<?php

require_once __ROOT__ . 'logger/TaggerLogManager.class.php';
require_once __ROOT__ . 'db/TaggerQueryManager.class.php';

class KeywordExtractor {
  public $words;
  public $tags;

  private $constant;

  function __construct($words) {
    $this->tagger = Tagger::getTagger();

    $this->tags = array();
    $this->constant = 1/count($words);

    $words = array_map('mb_strtolower', $words);
    $this->words = array_count_values($words);

  }

  public function determine_keywords() {
    $db_conf = $this->tagger->getConfiguration('db');
    $word_relations_table = $db_conf['word_relations_table'];
    $lookup_table = $db_conf['lookup_table'];

    $implode_words = implode("','", array_map('mysql_real_escape_string', array_keys($this->words)));

    $query = "SELECT * FROM $word_relations_table WHERE word IN ('$implode_words.')";
    TaggerLogManager::logDebug("Query:\n" . $query);
    $result = TaggerQueryManager::query($query);
    if ($result) {
      $subjects = array();

      while ($row = TaggerQueryManager::fetch($result)) {
        if (array_key_exists(mb_strtolower($row['word']), $this->words)) {
          if (!isset($subjects[$row['tid']]['rating'])) { $subjects[$row['tid']]['rating'] = 0; }
          //if(!isset($subjects[$row->tid]['words'])) { $subjects[$row->tid]['words'] = array(); }
          $subjects[$row['tid']]['rating'] += $row['score'] * $this->words[mb_strtolower($row['word'])];
          //$subjects[$row->tid]['words'][] = array('word' => $row->word, 'rating' => $row->score);
        }
      }

      $constant = $this->constant;
      // Normalize scores
      $normalize = function($s) use ($constant) {
        $s['rating'] *= $constant;
        return $s;
      };
      $subjects = array_map($normalize, $subjects);

      // Threshold
      $threshold = $this->tagger->getConfiguration('keyword_threshold');
      $thresher = function($subject) use ($threshold) {
        return $subject['rating'] > $threshold;
      };
      $subjects = array_filter($subjects, $thresher);

      //if (isset($subjects[0])) { unset($subjects[0]); }
      TaggerLogManager::logDebug("Keywords:\n" . print_r($subjects, true));

      if (!empty($subjects)) {
        $implode_subjects_ids = implode(',', array_map('mysql_real_escape_string', array_keys($subjects)));
        
        $query = "SELECT tid, vid, name FROM $lookup_table WHERE tid IN ($implode_subjects_ids)";
        $result = TaggerQueryManager::query($query);
        while ($row = TaggerQueryManager::fetch($result)) {
          $tag = new Tag($row['name']);
          $tag->rating = $subjects[$row['tid']]['rating'];
          $tag->realName = $row['name'];
          $this->tags[$row['vid']][$row['tid']] = $tag;
        }
      }
    }
    else {
      TaggerLogManager::logDebug("No keyword-relevant words found.");
    }
  }
}
