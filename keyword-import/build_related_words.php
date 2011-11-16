<?php

  require_once('lib_calc_score.php');

  // settings
  $property = 'diff_outer_doc_freq';
  $maximum_subject_add_count = 1; // how many subjects to be added at a time

  //touch('keyword_list_non_candidates.txt');
  $error = false;

  // Get selected subjects (subjects_selected.txt)
  /*if ($lines = file('subjects_selected.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
    foreach($lines as $line) {
      list($tid, $name) = explode('|', $line);
      $subjects[$tid] = mb_strtolower($name);
    }
    $c = count($lines);
    echo "Found $c subjects in subjects_selected.txt.\n";

    // check if the wanted subjects exists in the database
    foreach ($subjects as $tid => $name) {
      echo "$tid: $name\n";
      $query = "SELECT name FROM term_data WHERE vid = 16 AND tid = $tid";
      $result = mysql_query($query) or die(mysql_error());
      if ($row = mysql_fetch_object($result)) {
        if (mb_strtolower($row->name) == $name) {
          continue;
        }
        else {
          echo " => TID $tid is '$row->name' not '$name'.\n";
          unset($subjects[$tid]);
          $error = true;
        }
      }
      else {
        echo " => TID $tid not found.\n";
        unset($subjects[$tid]);
        $error = true;
      }
      $query = "SELECT tid FROM term_data WHERE vid = 16 AND name = '".mysql_real_escape_string($name)."'";
      $result = mysql_query($query) or die(mysql_error());
      if ($row = mysql_fetch_object($result)) {
        echo " => '$name' has TID $row->tid\n";
        $error = true;
      }
      else {
        echo " => No subject '$name' found i database.\n";
        $error = true;
      }
    }
  }
  else { */
    $query = "SELECT tid, name FROM term_data WHERE vid = 16";
    $result = mysql_query($query) or die(mysql_error());

    while ($row = mysql_fetch_object($result)) {
      $subjects[$row->tid] = $row->name;
    }
  //}

  if ($error) {
    die("Errors in subjects_selected.txt found. Exiting\n");
  }

  // filter subjects that:
  // * have too few articles (subject_non_candidates.txt)
  // * are already in the database (subjects_in_db.txt)
  // * or simply don't wanna have (add them yourself to subjects_non_candidates.txt)
  touch("subjects_non_candidates.txt");
  touch("subjects_in_db.$property.txt");
  $lines1 = file("subjects_non_candidates.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $lines2 = file("subjects_in_db.$property.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $lines = array_merge($lines1, $lines2);
  foreach($lines as $line) {
    list($tid, $name) = explode('|', $line);
    unset($subjects[$tid]);
  }

  //$subjects = array_slice($subjects, 0, $maximum_subject_add_count, true);

  $property_esc = mysql_real_escape_string($property);

  $subject_count = count($subjects);
  $new_subjects = 0;

  $start = time();
  echo "Trying to add $subject_count new subjects to the database...\n\n";

  foreach ($subjects as $tid => $name) {
    echo "Adding $name...\n";
    $name_esc = mysql_real_escape_string($name);

    // check if the subject already is in the database
    $query = "SELECT tid, word FROM `word_relations_$property_esc` WHERE tid = $tid";
    $result = mysql_query($query);
    if($result && mysql_num_rows($result) > 0) {
      echo "$name is already in the database. Skipping\n";
      $file = fopen("subjects_in_db.$property.txt", 'a');
      fwrite($file, $tid . '|' . $name . "\n");
      continue;
    }

    // Get and score the words related to this subject
    $result = subject_name_related_words($name, $property, 'underrubrik', false);

    $hits = $result['doc_count'];
    $freq_array = &$result['freq_array'];

    // too few articles found
    echo "$hits articles.";
    if ($hits < 5) {
      echo " Too few. Skipping.\n";
      $file = fopen('subjects_non_candidates.txt', 'a');
      fwrite($file, $tid . '|' . $name . '|' . $hits . "\n");
      continue;
    }
    else {
      echo '\n';
    }

    $file = fopen("subjects_in_db.$property.txt", 'a');
    fwrite($file, "$tid|$name|$hits\n");

    echo "Number of words possibly related to $name: " . count($freq_array) . "\n";

    // Create the table if it doesn't exist
    mysql_query("
      CREATE TABLE IF NOT EXISTS `word_relations_$property_esc` (
        `word` varchar(255) NOT NULL,
        `tid` varchar(255) NOT NULL,
        `score` decimal(30,20) unsigned NOT NULL,
        `pass` bigint(20) unsigned NOT NULL,
        KEY (`word`),
        KEY (`tid`)
      )  DEFAULT CHARSET=utf8;
    ") or die(mysql_error());

    $freq_array = array_filter($freq_array, function($v) use ($property) { return $v[$property] > 0.2; });

    $words_to_be_added = 0;
    foreach($freq_array as $value) {
      if ($words_to_be_added == 0) {
        $query = "INSERT INTO `word_relations_$property_esc` (word, tid, score, pass) VALUES\n";
      }
      else {
        $query .= ', ';
      }
      $query .= '(\''.mysql_real_escape_string($value['word']).'\','.$tid.','.$value[$property].',1)';
      $words_to_be_added++;

      if ($words_to_be_added == 1000) {
        if (!mysql_query($query)) {
          echo 'Could not query, line ' . __LINE__ . ': ' . mysql_error() . "<br>\n";
          echo $query . "\n";
          die();
        }
        $words_to_be_added = 0;
      }
    }

    if ($words_to_be_added != 0 && !mysql_query($query)) {
      echo 'Could not query, line ' . __LINE__ . ': ' . mysql_error() . "<br>\n";
      echo $query . "\n";
      die();
    }

    $new_subjects++;
  }

  $end = time();

  $time = $end - $start;

  echo "Added $new_subjects new subjects to the database.\n";
  echo 'Total time: '. $time .' secs. ('. @($time/$new_subjects) .' seconds per keywords)' . "\n";
  echo 'Total keywords in database: '. count(file("subjects_in_db.$property.txt", FILE_SKIP_EMPTY_LINES)) . "\n";

