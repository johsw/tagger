<?php

require_once __ROOT__ . 'db/TaggerQueryManager.class.php';

require_once __ROOT__ . 'classes/Tokenizer.class.php';
require_once __ROOT__ . 'classes/Stemmer.class.php';

//require_once('Timer.class.php');

class KeywordImporter {

  public function __construct() {
    $db_conf = Tagger::getConfiguration('db');
    $this->docstatsTable = $db_conf['docstats_table'];
    $this->wordstatsTable = $db_conf['wordstats_table'];
    $this->wordRelationsTable = $db_conf['word_relations_table'];
    $this->lookupTable = $db_conf['lookup_table'];

    $this->property = Tagger::getConfiguration('keyword', 'property');
    //$this->normalize = Tagger::getConfiguration('keyword', 'normalize');


    // Get total number of documents and words
    $query = "SELECT * FROM $this->docstatsTable LIMIT 0, 1";
    $result = TaggerQueryManager::query($query);
    $row = TaggerQueryManager::fetch($result);
    $this->totalDocCount = $row['doc_count'];
    $this->totalWordCount = $row['word_count'];
  }

  public function jsonCreateKeywords($filename = 'keywords.json') {
    $json = $this->jsonLoad($filename);

    $this->createKeywords($json);
  }

  public function jsonCreateWordstats($filename = 'keyword_texts.json') {
    $json = $this->jsonLoad($filename);

    $texts = array();
    foreach($json as $tid => $keyword_texts) {
      $texts = array_merge($texts, $keyword_texts);
    }

    return $this->createWordstats($texts);
  }

  public function jsonCreateWordRelations($filename = 'keyword_texts.json') {
    $json = $this->jsonLoad($filename);

    $this->createWordRelations($json);
  }


  private function jsonLoad($filename) {
    if (!is_file($filename)) {
      throw new Exception("No file named '$filename'.");
    }
    $file_contents = file_get_contents($filename);

    $json = json_decode($file_contents, TRUE);

    if ($json === NULL) {
      $err = json_errcode_to_text(json_last_error());
      throw new Exception("JSON $err.");
    }

    return $json;
  }

  /**
   * Fills the tagger_lookup table with keywords
   *
   * @param array $tids_keywords
   *   Array of keyword names {keys: tids, values: keywords}
   *   e.g. array(214 => 'Forest fires', ..)
   * @param bool $check
   *   Check whether keyword exists in database.
   *
   */
  protected function createKeywords($tids_keywords, $check = TRUE) {
    $fields = array('tid', 'vid', 'name', 'canonical');

    $values = array();
    foreach($tids_keywords as $tid => $keyword) {
      $values[] = array($tid, '16', $keyword, '1');
    }

    TaggerQueryManager::bufferedInsert($this->lookupTable, $fields, $values);
  }

  /**
   * Fills the tagger_word_relations table with words related to keywords (tids).
   *
   * @param array $tids_texts
   *   Array of arrays {keys: keyword tids, values: arrays texts}
   *   e.g. array(214 => array('Forest fire consumes city','Montana gets new seaplane'), ..)
   *   where 214 is a keyword tid and the array contains text related to that keyword
   * @param bool $check
   *   Check whether keyword exists in database.
   *
   */
  protected function createWordRelations($tids_texts, $check = TRUE) {

    $tids = array_keys($tids_texts);

    if ($check) {
      $error = false;

      $keywords = array();
      foreach ($tids as $tid) {

        // Get keyword corresponding to $tid
        if ($name = $this->tidToName($tid)) {
          echo "$tid: $name\n";
          $keywords[$tid] = $name;
        }
        else {
          echo "$tid: Not found.\n";
          unset($tids_texts[$tid]);
          $error = true;
        }
      }


      // filter keywords that:
      // * have too few articles (keyword_non_candidates.txt)
      // * are already in the database (keywords_in_db.txt)
      // * or simply don't wanna have (add them yourself to keywords_non_candidates.txt)
      touch("keywords_non_candidates.txt");
      touch("keywords_in_db.$this->property.txt");
      $lines1 = file("keywords_non_candidates.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $lines2 = file("keywords_in_db.$this->property.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $lines = array_merge($lines1, $lines2);
      foreach($lines as $line) {
        list($tid, $name) = explode('|', $line);
        unset($keywords[$tid]);
      }
 
      $keyword_count = count($keywords);
    }
    else {
      $keyword_count = count(array_keys($tid));
    }

    if ($error) {
      die("Errors in TIDs found. Exiting\n");
    }

    //$keywords = array_slice($keywords, 0, $maximum_keyword_add_count, true);

    $property_esc = mysql_escape_string($this->property);

    $new_keywords = 0;

    $start = time();
    echo "Trying to add $keyword_count new keywords to the database...\n\n";

    foreach ($tids_texts as $tid => $texts) {

      // Create the related words to this keyword
      if (!empty($texts)) {
        echo "Adding $name...\n";
        $result = $this->createRelatedWords($tid, $texts, FALSE);

        if($result) {
          $new_keywords++;
        }
      }
    }
  }

  public function createRelatedWords($tid, $texts, $check = TRUE) {

    if ($check) {

      // too few articles found
      if (count($texts) < Tagger::getConfiguration('keyword', 'minimum_number_of_texts')) {
        throw new Exception("Too few texts (" . count($texts) . " for " . $this->tidToName($tid));
      }
      /*
      echo "$hits articles.";
      if ($hits < 5) {
        echo " Too few. Skipping.\n";
        $file = fopen('keywords_non_candidates.txt', 'a');
        fwrite($file, $tid . '|' . $name . '|' . $hits . "\n");
        continue;
      }
      else {
        echo '\n';
      }
      */

    }

    // Check if $tid is a keyword in the DB
    $name = $this->tidToName($tid);
    echo "tid: $tid\n";

    // Check if words related to $tid are already in the DB
    $query = "SELECT tid, word FROM `$this->wordRelationsTable` WHERE tid = $tid";
    $result = TaggerQueryManager::query($query);
    if(TaggerQueryManager::fetch($result)) {
      echo "$name is already in the database. Skipping\n";
      $file = fopen("keywords_in_db.$this->property.txt", 'a');
      fwrite($file, $tid . '|' . $name . "\n");
      return FALSE;
    }

    $property_esc = mysql_real_escape_string($this->property);

    // Get and score the words related to this keyword
    $result = $this->findSignificantWords($texts);
    $hits = $result['doc_count'];
    $freq_array = &$result['freq_array'];


    echo "Number of words possibly related to $name: " . count($freq_array) . "\n";

    $freq_array = array_filter($freq_array, create_function('$v', 'return $v[\'' . $this->property . '\'] > 0.2; '));

    $values_array = array();

    $words_to_be_added = 0;
    foreach($freq_array as $value) {
      $values_array[] = array($value['word'], $tid, $value[$this->property],1);
      $words_to_be_added++;
    }

    $fields = array('word', 'tid', 'score', 'pass');
    TaggerQueryManager::bufferedInsert($this->wordRelationsTable, $fields, $values_array);

    // Added to DB
    $file = fopen("keywords_in_db.$this->property.txt", 'a');
    fwrite($file, "$tid|$name|$hits\n");
  }

  function keyword_update($tid, $texts) {

    if (count($texts) < Tagger::getConfiguration('keyword', 'minimum_number_of_texts')) {
      throw new Exception("Too few texts (" . count($texts) . " for " . $this->tidToName($tid));
    }

    $query = "DELETE FROM `$this->wordRelationsTable` WHERE tid = $tid";
    TaggerQueryManager::query($query);

    keyword_create($tid, $texts);
  }



  function findSignificantWords($texts, $prop = FALSE, $normalize = FALSE) {

    if ($prop === FALSE) {
      $prop = $this->property;
    }

    //$timer = new Timer();

    $doc_count = 0;
    $word_count = 0;
    $doc_ids = array();
    $freq_array = array();

    //$timer->start();

    foreach ($texts as $text) {
      if($text != '') {
        $frequency = $this->scoreText($text);

        foreach ($frequency AS $key => $value) {
          $word_count += $value['word_count'];
          if (isset($freq_array[$key])) {
            $freq_array[$key]['word_count'] += $value['word_count'];
            $freq_array[$key]['doc_count']++;
          }
          else {
            $freq_array[$key]['word_count'] = $value['word_count'];
            $freq_array[$key]['doc_count'] = 1;

            $freq_array[$key]['word'] = $value['word'];
            $freq_array[$key]['doc_count_db'] = $value['doc_count'];
            $freq_array[$key]['word_freq_db'] = $value['word_freq_db'];
            $freq_array[$key]['doc_freq_db'] = $value['doc_freq_db'];
          }
        }
        $doc_count++;
      }
    }

    if ($prop == 'all') {
      $properties = array('diff', 'doc_freq', 'inner_doc_freq', 'outer_doc_freq',
                          'diff_outer_doc_freq', 'diff_outer_doc_freq_log');
    }
    else {
      $properties = array($prop);
    }

    foreach ($freq_array as $key => &$elem) {
      $temp_elem = $elem;

      $temp_elem['diff'] = $temp_elem['word_count']/$word_count - $temp_elem['word_freq_db'];

      $temp_elem['doc_freq'] = $temp_elem['doc_count_db']/$this->totalDocCount;

      // in how many related articles does this word occur? (relative to the number of related articles)
      // i.e. the percentage of related articles where this word occurs
      $temp_elem['inner_doc_freq'] = ($temp_elem['doc_count']-1)/$doc_count;
      // in how many related articles does this word occur? 
      // (relative to the total number of articles where this word occurs)
      // i.e. the percentage of articles in which this word occurs that are related to this keyword
      $temp_elem['outer_doc_freq'] = min(($temp_elem['doc_count']-1)/(1+$temp_elem['doc_count_db']), 1);

      $temp_elem['malthe'] = $temp_elem['outer_doc_freq'] * $temp_elem['inner_doc_freq'];

      //$temp_elem['malt_x2'] = pow($temp_elem['doc_count'],2)/(1+$temp_elem['doc_count_db']);
      //$temp_elem['diff_malt_x2'] = $temp_elem['diff'] * $temp_elem['malt_x2'];
      //$temp_elem['diff_malt_x2_log'] = log(10000*$temp_elem['diff_malt_x2'], 2);
      //$temp_elem['diff_malt_x2_sqr'] = sqrt($temp_elem['diff_malt_x2']);

      $temp_elem['diff_outer_doc_freq'] = $temp_elem['diff'] * $temp_elem['outer_doc_freq'] * 10000;
      $temp_elem['diff_outer_doc_freq_log'] = log10($temp_elem['diff_outer_doc_freq']+1);

      if ($prop == 'all') {
        $elem = $temp_elem;
        foreach ($properties as $p) {
          if (is_nan($elem[$p])) {
            unset($freq_array[$key]);
          }
        }
      }
      else {
        if (is_nan($temp_elem[$prop])) {
          unset($freq_array[$key]);
        }
        else {
          $elem[$prop] = $temp_elem[$prop];
        }
      }
    }

    if ($normalize && $doc_count > 0) {
      foreach ($properties as $p) {
        // get the word with the highest score
        $val = max(array_map(create_function('$value', 'return $value[$p];'), $freq_array));
        $val = ($val == 0) ? 1 : $val;
        $factor = 1/$val;
        
        // divide any other score by that (the largest) score 
        foreach($freq_array as &$value) {
          $value[$p] *= $factor;
        }
      }
    }

    //$timer->stop();
    
    //echo "Calculations took " . $timer->secsElapsed() . " seconds.\n";

    $result =  array();
    $result['doc_count'] = $doc_count;
    $result['freq_array'] = &$freq_array;

    return $result;
  }


  public function createWordstats($texts) {
    $sql = "TRUNCATE $this->wordstatsTable";
    TaggerQueryManager::query($sql);

    list($this->totalDocCount, $this->totalWordCount) = $this->calculateWordstats($texts);

    // Set document frequency and word frequency for all words i.e. all rows in the DB
    $sql = "UPDATE $this->wordstatsTable
            SET doc_freq=doc_count/$this->totalDocCount,
                word_freq=word_count/$this->totalWordCount";
    TaggerQueryManager::query($sql);

    // Sets a table (docstats) with the total number of words and documents
    $sql = "TRUNCATE $this->docstatsTable";
    TaggerQueryManager::query($sql);
    $sql = "INSERT INTO `$this->docstatsTable` (doc_count,word_count)
            VALUES ($this->totalDocCount, $this->totalWordCount);";
    TaggerQueryManager::query($sql);

    return array($this->totalDocCount, $this->totalWordCount);
  }

  public function calculateWordstats($texts) {

    $doc_count = 0;
    $word_count = 0;
    $overall_frequency = array();

    foreach ($texts as $text) {
      if ($text != '') {
        $frequency = $this->countWords($text);

        foreach ($frequency AS $key => $value){
          $word_count += $value['word_count'];
          if(!isset($overall_frequency[$key])) {
            $overall_frequency[$key]['word_count'] = $value['word_count'];
            $overall_frequency[$key]['doc_count'] = 1;
            $overall_frequency[$key]['doc_freq_sum'] = $value['word_freq'];
          } else {
            $overall_frequency[$key]['word_count'] += $value['word_count'];
            $overall_frequency[$key]['doc_count'] += 1;
            $overall_frequency[$key]['doc_freq_sum'] += $value['word_freq'];
          }
        }
        $doc_count++;

        if(($doc_count % 1000) == 0) {
          $this->updateWordstatsTable($overall_frequency);
          $overall_frequency = array();
        }
      }
    }

    return array($doc_count, $word_count);
  }


  function updateWordstatsTable($frequencies) {

    $counter = 0;

    foreach ($frequencies AS $key => $value) {
      if($counter == 0) {
        $sql = "INSERT INTO $this->wordstatsTable (word, word_count, doc_count) VALUES\n";
      }
      else {
        $sql .= ', ';
      }

      $key = mysql_escape_string($key);
      $sql .= "('$key', $value[word_count], $value[doc_count])";

      $counter++;

      if($counter == 1000) {
        $sql .= " ON DUPLICATE KEY UPDATE word_count=word_count+VALUES(word_count),
                                          doc_count=doc_count+VALUES(doc_count);";
        TaggerQueryManager::query($sql);
        $counter = 0;
      }
    }
    $sql .= " ON DUPLICATE KEY UPDATE word_count=word_count+VALUES(word_count),
                                      doc_count=doc_count+VALUES(doc_count);";
    TaggerQueryManager::query($sql);
  }

  // Calculate word scores in text
  private function scoreText($text) {
    static $db_cache = array();


    $frequency = $this->countWords($text);
    $words_to_lookup = array_diff(array_keys($frequency), array_keys($db_cache));

    $imploded_words = implode("','", array_map('mysql_real_escape_string', $words_to_lookup));

    // Get statistics for the words in the article
    $result = TaggerQueryManager::query("SELECT * FROM $this->wordstatsTable WHERE word IN ('$imploded_words')");

    $unmatched_database = array();
    $unmatched_words = $frequency;
    while ($row = TaggerQueryManager::fetch($result)) {
      // words in the database are assumed to be in mb-lowercase
      // (mb_strtolower)
      $key = $row['word'];

      if(isset($frequency[$key])) {
        unset($unmatched_words[$key]);
        $cur_elem = &$frequency[$key];
        $cur_elem['word'] = $row['word'];
        $cur_elem['word_freq_db'] = $row['word_freq'];
        $cur_elem['doc_count'] = $row['doc_count'];
        $cur_elem['doc_freq_db'] = $row['doc_freq'];
      } else {
        $unmatched_database[] = $key;
      }
    }

    foreach ($unmatched_words as $key => $val) {
      unset($frequency[$key]);
    }

    return $frequency;
  }


  // Get word frequencies for a text
  private function countWords($text) {
    if ($text == '') {
      return array();
    }
    if (is_array($text)) {
      //var_dump($text);
    }

    $words = Tokenizer::split_words(trim(mb_strtolower(strip_tags($text))));
    if (Tagger::getConfiguration('keyword', 'enable_stemmer')) {
      foreach ($words as &$word) {
        $word = Stemmer::stemWord($word);
      }
    }
    $frequency = array_count_values($words);
    $frequency = array_diff_key($frequency, Tagger::$stopwords);

    $word_count = array_sum($frequency);

    mb_regex_encoding("UTF-8");
    foreach ($frequency as $key => $value) {
      if (!mb_ereg_match('\w', $key)) {
        unset($frequency[$key]);
      }
    }
    //arsort($frequency);

    foreach($frequency as $key => $value){
      $frequency[$key] = array('word_count' => $value, 'word_freq' => $value/$word_count);
    }
    return $frequency;
  }

  public function tidToName($tid) {

    $query = "SELECT name FROM $this->lookupTable WHERE vid = 16 AND tid = $tid AND canonical = 1";
    $result = TaggerQueryManager::query($query);
    if ($row = TaggerQueryManager::fetch($result)) {
      return $row['name'];
    }
    else {
      return FALSE;
    }
  }

  private function json_errcode_to_text($errcode) {
    $err = '';
    switch ($errcode) {
      case JSON_ERROR_NONE:
          $err = ' - No errors';
      break;
      case JSON_ERROR_DEPTH:
          $err = ' - Maximum stack depth exceeded';
      break;
      case JSON_ERROR_STATE_MISMATCH:
          $err = ' - Underflow or the modes mismatch';
      break;
      case JSON_ERROR_CTRL_CHAR:
          $err = ' - Unexpected control character found';
      break;
      case JSON_ERROR_SYNTAX:
          $err = ' - Syntax error, malformed JSON';
      break;
      case JSON_ERROR_UTF8:
          $err = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
      break;
      default:
          $err = ' - Unknown error';
      break;
    }

      return $err;
  }

}

