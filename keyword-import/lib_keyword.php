<?php

  require_once('../Tagger.php');
  require_once __ROOT__ . 'db/TaggerQueryManager.class.php';

  $tagger = Tagger::getTagger();
  $options = $tagger->getConfiguration();

  $db_conf =$tagger->getConfiguration('db');
  $docstats_table = $db_conf['docstats_table'];
  $wordstats_table = $db_conf['wordstats_table'];
  $word_relations_table = $db_conf['word_relations_table'];

  $lookup_table = $db_conf['lookup_table'];

  $keyword_conf = $tagger->getConfiguration('keyword');
  $property = $keyword_conf['property'];
  $normalize = $keyword_conf['normalize'];


  // Get total number of documents and words
  $query = "SELECT * FROM $docstats_table LIMIT 0, 1";
  $result = TaggerQueryManager::query($query);
  $row = TaggerQueryManager::fetch($result);
  $total_doc_count = $row['doc_count'];
  $total_word_count = $row['word_count'];

  function json_load($filename) {
    if (!is_file($filename)) {
      throw new Exception("No file named '$filename'.");
    }
    $file_contents = file_get_contents($filename);

    $json = json_decode($file_contents, true);

    if ($json === NULL) {
      $err = json_errcode_to_text(json_last_error());
      throw new Exception("JSON $err.");
    }

    return $json;
  }

  function json_create_wordstats($filename = 'keyword_texts.json') {
    $json = json_load($filename);

    function flatten(array $array) {
      $return = array();
      array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
      return $return;
    }

    return create_wordstats(flatten(array_values($json)));
  }

  function json_create_keywords($filename = 'keyword_texts.json') {
    $json = json_load($filename);

    create_keywords($json);
  }

  function create_keywords($tids_n_texts, $check = true) {
    global $tagger, $property, $docstats_table, $wordstats_table, $word_relations_table;

    $tids = array_keys($tids_n_texts);

    if ($check) {
      $error = false;

      $keywords = array();
      foreach ($tids as $tid) {

        // Get keyword corresponding to $tid
        if ($name = tid_to_name($tid)) {
          $keywords[$tid] = $name;
        }
        else {
          echo "$tid: Not found.\n";
          unset($tids_n_texts[$tid]);
          $error = true;
        }
      }
      echo "$tid: $name\n";


      // filter keywords that:
      // * have too few articles (keyword_non_candidates.txt)
      // * are already in the database (keywords_in_db.txt)
      // * or simply don't wanna have (add them yourself to keywords_non_candidates.txt)
      touch("keywords_non_candidates.txt");
      touch("keywords_in_db.$property.txt");
      $lines1 = file("keywords_non_candidates.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $lines2 = file("keywords_in_db.$property.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $lines = array_merge($lines1, $lines2);
      foreach($lines as $line) {
        list($tid, $name) = explode('|', $line);
        unset($keywords[$tid]);
      }

    }

    if ($error) {
      die("Errors in TIDs found. Exiting\n");
    }

    //$keywords = array_slice($keywords, 0, $maximum_keyword_add_count, true);

    $property_esc = mysql_real_escape_string($property);

    $keyword_count = count($keywords);
    $new_keywords = 0;

    $start = time();
    echo "Trying to add $keyword_count new keywords to the database...\n\n";

    foreach ($keywords as $tid => $name) {
      echo "Adding $name...\n";

      $name_esc = mysql_real_escape_string($name);

      // Create the related words to this keyword
      $result = keyword_create($tid, $tids_n_texts[$tid], FALSE);

      if($result) {
        $new_keywords++;
      }
    }
  }

  function keyword_create($tid, $texts, $check = TRUE) {
    global $tagger, $options, $property, $word_relations_table;

    if ($check) {

      // too few articles found
      if (count($texts) < $options['keyword']['minimum_number_of_texts']) {
        throw new Exception("Too few texts (" . count($texts) . " for " . tid_to_name($tid));
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
    $name = tid_to_name($tid);
    echo "tid: $tid\n";

    // Check if words related to $tid are already in the DB
    $query = "SELECT tid, word FROM `$word_relations_table` WHERE tid = $tid";
    $result = TaggerQueryManager::query($query);
    if(TaggerQueryManager::fetch($result)) {
      echo "$name is already in the database. Skipping\n";
      $file = fopen("keywords_in_db.$property.txt", 'a');
      fwrite($file, $tid . '|' . $name . "\n");
      return FALSE;
    }

    $property_esc = mysql_real_escape_string($property);

    // Get and score the words related to this keyword
    $result = find_significant_words($texts);
    $hits = $result['doc_count'];
    $freq_array = &$result['freq_array'];


    echo "Number of words possibly related to $name: " . count($freq_array) . "\n";

    $freq_array = array_filter($freq_array, function($v) use ($property) { return $v[$property] > 0.2; });

    $words_to_be_added = 0;
    foreach($freq_array as $value) {
      if ($words_to_be_added == 0) {
        $query = "INSERT INTO `$word_relations_table` (word, tid, score, pass) VALUES\n";
      }
      else {
        $query .= ', ';
      }
      $query .= '(\''.mysql_real_escape_string($value['word']).'\','.$tid.','.$value[$property].',1)';
      $words_to_be_added++;

      if ($words_to_be_added == 1000) {
        if (!TaggerQueryManager::query($query)) {
          // ******* TO BE REMOVED *******
          echo 'Could not query, line ' . __LINE__ . ': ' . mysql_error() . "<br>\n";
          echo $query . "\n";
          die();
        }
        $words_to_be_added = 0;
      }
    }

    if ($words_to_be_added != 0 && !TaggerQueryManager::query($query)) {
      // ******* TO BE REMOVED *******
      echo 'Could not query, line ' . __LINE__ . ': ' . mysql_error() . "<br>\n";
      echo $query . "\n";
      die();
    }

    // Added to DB
    $file = fopen("keywords_in_db.$property.txt", 'a');
    fwrite($file, "$tid|$name|$hits\n");
  }

  function keyword_update($tid, $texts) {
    global $options, $word_relations_table;

    if (count($texts) < $options['keyword']['minimum_number_of_texts']) {
      throw new Exception("Too few texts (" . count($texts) . " for " . tid_to_name($tid));
    }

    $query = "DELETE FROM `$word_relations_table` WHERE tid = $tid";
    TaggerQueryManager::query($query);

    keyword_create($tid, $texts);
  }


  require_once('Timer.class.php');

  function find_significant_words($texts, $prop = FALSE, $normalize = FALSE) {
    global $property, $total_doc_count;

    if ($prop === FALSE) {
      $prop = $property;
    }

    $timer = new Timer();

    $doc_count = 0;
    $word_count = 0;
    $doc_ids = array();
    $freq_array = array();

    $timer->start();

    foreach ($texts as $text) {

      $frequency = score_text($text);

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

      $temp_elem['doc_freq'] = $temp_elem['doc_count_db']/$total_doc_count;

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
        $val = max(array_map(function($value) use ($p) { return $value[$p]; }, $freq_array));
        $val = ($val == 0) ? 1 : $val;
        $factor = 1/$val;
        
        // divide any other score by that (the largest) score 
        foreach($freq_array as &$value) {
          $value[$p] *= $factor;
        }
      }
    }

    $timer->stop();
    
    echo "Calculations took " . $timer->secsElapsed() . " seconds.\n";

    $result =  array();
    $result['doc_count'] = $doc_count;
    $result['freq_array'] = &$freq_array;

    return $result;
  }


  function create_wordstats($texts) {
    init_wordstats_table();
    list($doc_count, $word_count) = calculate_wordstats($texts);
    end_wordstats_table($doc_count, $word_count);

    create_docstats_table($doc_count, $word_count);

    return array($doc_count, $word_count);
  }

  function calculate_wordstats($texts) {

    $doc_count = 0;
    $word_count = 0;
    $overall_frequency = array();

    foreach ($texts as $text) {

      $frequency = count_words($text);

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
        update_wordstats_table($overall_frequency);
        $overall_frequency = array();
      }
    }

    return array($doc_count, $word_count);
  }

  function init_wordstats_table() {
    global $wordstats_table;


  }

  function update_wordstats_table($frequencies) {
    global $wordstats_table;

    $counter = 0;

    foreach ($frequencies AS $key => $value) {
      if($counter == 0) {
        $sql = "INSERT INTO $wordstats_table (word, word_count, doc_count) VALUES\n";
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

  function end_wordstats_table($doc_count, $word_count) {
    global $wordstats_table;
    $sql = "UPDATE $wordstats_table SET doc_freq=doc_count/$doc_count, word_freq=word_count/$word_count";
    TaggerQueryManager::query($sql);
  }


  function create_docstats_table($doc_count, $word_count) {
    global $docstats_table;

    TaggerQueryManager::query("INSERT INTO `$docstats_table` (doc_count,word_count) VALUES ($doc_count,$word_count);");
  }

  // Calculate word scores in text
  function score_text($text) {
    global $total_doc_count, $tagger;
    static $db_cache = array();

    $wordstats_table = $tagger->getConfiguration('db', 'wordstats_table');

    $frequency = count_words($text);
    $words_to_lookup = array_diff(array_keys($frequency), array_keys($db_cache));

    $imploded_words = implode("','", array_map('mysql_real_escape_string', $words_to_lookup));

    // Get statistics for the words in the article
    $result = TaggerQueryManager::query("SELECT * FROM $wordstats_table WHERE word IN ('$imploded_words')");

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

  require_once __ROOT__ . 'classes/Token.class.php';
  require_once __ROOT__ . 'classes/Tokenizer.class.php';
  require_once __ROOT__ . 'classes/Stemmer.class.php';

  // Get word frequencies for a text
  function count_words($text) {
    $tagger = Tagger::getTagger();
    $options_keyword = $tagger->getConfiguration('keyword');

    $words = Tokenizer::split_words(trim(mb_strtolower(strip_tags($text))));
    if ($options_keyword['stemmer']) {
      foreach ($words as &$word) {
        $word = Stemmer::stemWord($word);
      }
    }
    $frequency = array_count_values($words);
    $t = new Token('t');
    $frequency = array_diff_key($frequency, $t::$stopwords);

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

  function tid_to_name($tid) {
    global $lookup_table;

    $query = "SELECT name FROM $lookup_table WHERE vid = 16 AND tid = $tid AND canonical = 1";
    $result = TaggerQueryManager::query($query);
    if ($row = TaggerQueryManager::fetch($result)) {
      return $row['name'];
    }
    else {
      return FALSE;
    }
  }

  function json_errcode_to_text($errcode) {
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


