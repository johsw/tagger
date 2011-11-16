<?php

  require_once('../Tagger.php');
  require_once __ROOT__ . 'db/TaggerQueryManager.class.php';
  $tagger = Tagger::getTagger();


  require_once('lib_keyword.php');


  function inf_generate_json() {
    $texts = inf_get_multiple_keywords_texts(3);
    $json = json_encode($texts);
    file_put_contents('keyword_texts.json', $json);
  }

  function inf_create_keywords() {
    multiple_keywords_create(inf_get_multiple_keywords_texts(3), $check = true);
  }

  function inf_get_multiple_keywords_texts($keyword_count = 1) {
    // $keyword_count: how many keywords to be added at a time

    global $tagger;
    $keyword_conf = $tagger->getConfiguration('keyword');
    $property = $keyword_conf['property'];

    $db_conf = $tagger->getConfiguration('db');
    $word_relations_table = $db_conf['word_relations_table'];


	  touch('keywords_selected.txt');
	  $error = false;

	  // Get selected keywords (keywords_selected.txt)
	  if ($lines = file('keywords_selected.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
	    foreach($lines as $line) {
	      list($tid, $name) = explode('|', $line);
	      $keywords[$tid] = mb_strtolower($name);
	    }
	    $c = count($lines);
	    echo "Found $c keywords in keywords_selected.txt.\n";

	    // check if the wanted keywords exists in the database
	    foreach ($keywords as $tid => $name) {
	      echo "$tid: $name\n";
	      $query = "SELECT name FROM term_data WHERE vid = 16 AND tid = $tid";
	      $result = TaggerQueryManager::query($query);
	      if ($row = TaggerQueryManager::fetch($result)) {
	        if (mb_strtolower($row['name']) == $name) {
	          continue;
	        }
	        else {
	          echo " => TID $tid is '".$row['name']."' not '$name'.\n";
	          unset($keywords[$tid]);
	          $error = true;
	        }
	      }
	      else {
	        echo " => TID $tid not found.\n";
	        unset($keywords[$tid]);
	        $error = true;
	      }
	      $query = "SELECT tid FROM term_data WHERE vid = 16 AND name = '".mysql_real_escape_string($name)."'";
	      $result = TaggerQueryManager::query($query);
	      if ($row = TaggerQueryManager::fetch($result)) {
	        echo " => '$name' has TID ".$row['tid']."\n";
	        $error = true;
	      }
	      else {
	        echo " => No keyword '$name' found i database.\n";
	        $error = true;
	      }
	    }
	  }
	  else { 
	    $query = "SELECT tid, name FROM term_data WHERE vid = 16";
	    $result = TaggerQueryManager::query($query);

	    while ($row = TaggerQueryManager::fetch($result)) {
	      $keywords[$row['tid']] = $row['name'];
	    }
	  }

    if ($error) {
      die("Errors in keywords_selected.txt found. Exiting\n");
    }

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

    $keywords = array_slice($keywords, 0, $keyword_count, true);

    $keyword_count = count($keywords);
    $new_keywords = 0;

    $start = time();

    $tids_n_texts = array();
    foreach ($keywords as $tid => $name) {
      echo "Finding articles related to $name...\n";

      // Get texts related to this keyword
      $tids_n_texts[$tid] = inf_get_keyword_texts($name, $property, 'underrubrik');
      $hits = count($tids_n_texts[$tid]);

      // too few articles found
      echo "$hits articles.";
      if ($hits < 5) {
        unset($tids_n_texts[$tid]);
        echo " Too few. Skipping.\n\n";
        $file = fopen('keywords_non_candidates.txt', 'a');
        fwrite($file, $tid . '|' . $name . '|' . $hits . "\n");
        continue;
      }
      else {
        echo "\n\n";
      }
    }
    return $tids_n_texts;
  }

  function inf_get_keyword_texts($name, $property = 'all', $range = 'overskrift', $normalize = false) {
    global $total_doc_count;

    $timer = new Timer();

    $name_esc = mysql_real_escape_string($name);

    $articles_sql = "SELECT n.nid, nr.vid, nr.title, nr.body, cfu.field_underrubrik_value  FROM `node` AS n
                           JOIN `node_revisions` AS nr ON n.vid = nr.vid
                           LEFT JOIN content_field_underrubrik AS cfu ON cfu.vid = n.vid 
                           WHERE n.type IN ('avisartikel', 'ritzau_telegram')";
    if ($range == 'underrubrik') {
      // earlier search - gave problems with the keyword 'dans' that gave articles with
      // 'dansk', 'danske' osv.
      //$articles_sql .= " AND (n.title REGEXP '[[:<:]]$name_esc'
      //                      OR cfu.field_underrubrik_value REGEXP '[[:<:]]$name_esc')";

      $articles_sql .= " AND (n.title REGEXP '[[:<:]]".$name_esc."[[:>:]]'
                            OR cfu.field_underrubrik_value REGEXP '[[:<:]]".$name_esc."[[:>:]]')";
    }
    elseif ($range == 'fulltext') {
      $articles_sql .= " AND (nr.title LIKE '%$name_esc%'
                            OR cfu.field_underrubrik_value REGEXP '%$name_esc%'
                            OR nr.body LIKE '%$name_esc%')";
    }
    elseif ($range == 'tagged') {
      $sql = "SELECT tid FROM term_data WHERE name = '$name_esc' AND vid = 16";
      $result = mysql_query($sql) or die(mysql_error());
      $row = mysql_fetch_object($result);
      $tid = $row['tid'];

      $articles_sql = "SELECT n.nid, nr.vid, nr.title, nr.body, cfu.field_underrubrik_value  FROM `node` AS n
                             JOIN `node_revisions` AS nr ON n.vid = nr.vid
                             JOIN content_field_underrubrik AS cfu ON cfu.vid = n.vid 
                             JOIN term_node AS tn ON tn.vid = n.vid
                             WHERE n.type IN ('avisartikel', 'ritzau_telegram')
                             AND tn.tid = $tid AND n.nid > 230908";
    }
    else {
      // search only in headlines
    
      // the problem with the LIKE query below is that 'astronomi' matches 'gastronomi'
      $articles_sql .= " AND n.title LIKE '%" . $name_esc . "%'";

      // that doesn't happen in this REGEXP query
      //$articles_sql .= " AND n.title REGEXP '[[:<:]]" . $name_esc . "'";
      //$articles_sql .= " AND n.title REGEXP '[[:<:]]" . $name_esc . "[[:>:]]'";
    }
    //echo $articles_sql;

    $timer->start();
    $articles_result = TaggerQueryManager::query($articles_sql);
    $timer->stop();

    echo "Query took " . $timer->secsElapsed() . " seconds.\n";


    $doc_count = 0;
    $word_count = 0;
    $doc_ids = array();
    $freq_array = array();

    $texts = array();
    while ($articles_row = TaggerQueryManager::fetch($articles_result)) {
      $doc_ids[] = $articles_row['nid'];
      $texts[] = strip_tags($articles_row['title'].' '.$articles_row['field_underrubrik_value']. ' '.$articles_row['body']);
      $doc_count++;
    }

    //$result =  array();
    //$result['doc_ids'] = $doc_ids;
    //$result['doc_count'] = $doc_count;
    
    return $texts;
  }

  // Calculate word scores in article
  function score_article($id) {
    global $text;

    $query = '
      SELECT nr.title, nr.body, cfu.field_underrubrik_value  
      FROM node AS n
      JOIN node_revisions AS nr ON nr.vid = n.vid
      JOIN content_field_underrubrik AS cfu ON cfu.vid = n.vid 
      WHERE n.type = "avisartikel" AND n.nid = '. $id .'
      LIMIT 0, 1';

    $result = TaggerQueryManager::query($query);
    if($row = TaggerQueryManager::fetch($result)){
      print "No article with id=" . $id; exit;
    }
   

    $text = strip_tags($row['title'].' '.$row['field_underrubrik_value']. ' '.$row['body']);

    return score_text($text);
  }

  function get_all_texts() {
    
    $query = '
      SELECT nr.title, nr.body, cfu.field_underrubrik_value
      FROM node AS n
      JOIN node_revisions AS nr ON nr.vid = n.vid
      JOIN content_field_underrubrik AS cfu ON cfu.vid = n.vid
      WHERE n.type = "avisartikel"
      ORDER BY created DESC
      LIMIT 0, 10000;';

    $result = mysql_query($query);

    while ($row = mysql_fetch_object($result)) {
      $texts[] = count_words(strip_tags($row->title.' '.$row->field_underrubrik_value. ' '.$row->body));
    }

    return $texts;
  }
