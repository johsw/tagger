<?php
  ini_set('memory_limit', '1024M');
  ini_set('extension', 'translit.so');

  require_once 'lib_calc_score.php';

  $start = time();

  $link = mysql_connect('localhost', 'root', 'sniggle');

  if (!$link) {
    die('Could not connect: ' . mysql_error());
  }
  mysql_select_db('ny_taggerdk');
  mysql_set_charset ('utf8', $link);
  mb_internal_encoding("UTF-8");

  mysql_query('
    CREATE TABLE IF NOT EXISTS `wordstats` (
      `word` varchar(255) NOT NULL,
      `word_count` bigint(20) unsigned NOT NULL,
      `doc_count` bigint(20) unsigned NOT NULL,
      `word_freq` decimal(30,20) unsigned NOT NULL,
      `doc_freq` decimal(30,20) unsigned NOT NULL,
      `doc_freq_std` decimal(30,20) unsigned NOT NULL,
      PRIMARY KEY (`word`)
    )  DEFAULT CHARSET=utf8;
  ');

  if (!mysql_query('TRUNCATE TABLE `wordstats`;')) {
    die('Could not query:' . mysql_error());
  }

  $query = '
    SELECT nr.title, nr.body, cfu.field_underrubrik_value
    FROM node AS n
    JOIN node_revisions AS nr ON nr.vid = n.vid
    JOIN content_field_underrubrik AS cfu ON cfu.vid = n.vid
    WHERE n.type = "avisartikel"
    ORDER BY created DESC
    LIMIT 0, 10000;';

  $result = mysql_query($query);
  $doc_count = 0;
  $word_count = 0;


  $overall_frequency = array();
  while ($row = mysql_fetch_object($result)) {

    $frequency = count_words(strip_tags($row->title.' '.$row->field_underrubrik_value. ' '.$row->body));

    foreach ($frequency AS $key => $value){
      $word_count += $value['word_count'];
      if(!isset($overall_frequency[$key])) {
        $overall_frequency[$key]['word_count'] = $value['word_count'];
        $overall_frequency[$key]['doc_count'] = 1;
        $overall_frequency[$key]['doc_freq_sum'] = $value['word_freq'];
        $overall_frequency[$key]['doc_freq_squared_sum'] = pow($value['word_freq'],2);
      } else {
        $overall_frequency[$key]['word_count'] += $value['word_count'];
        $overall_frequency[$key]['doc_count'] += 1;
        $overall_frequency[$key]['doc_freq_sum'] += $value['word_freq'];
        $overall_frequency[$key]['doc_freq_squared_sum'] += pow($value['word_freq'],2);
      }
    }
    $doc_count++;
  }

  $counter = 0;
  $sql = "INSERT INTO wordstats (word, word_count, doc_count, word_freq, doc_freq, doc_freq_std) VALUES\n";
  foreach ($overall_frequency AS $key => $value) {
    $key = mysql_escape_string($key);
    if($counter != 0) {
      $sql .= ', ';
    }
    $word_freq = $value['word_count']/$word_count;
    $doc_freq = $value['doc_freq_sum']/$doc_count;
    $doc_freq_std = sqrt($value['doc_freq_squared_sum']/$doc_count - pow($doc_freq,2));
    $sql .= "('$key', $value[word_count], $value[doc_count], $word_freq, $doc_freq, $doc_freq_std)";
    if(++$counter == 1000) {
      $sql .= " ON DUPLICATE KEY UPDATE word_count=word_count+VALUES(word_count),
                                        doc_count=doc_count+VALUES(doc_count),
                                        word_freq=(word_count+VALUES(word_count))/$word_count,
                                        doc_freq=(doc_freq+VALUES(doc_freq)),
                                        doc_freq_std=(doc_freq_std+VALUES(doc_freq_std))/2;";
      // the last two lines in ON DUPLICATE KEY UPDATE are not correct but an approximation!
      if (!mysql_query($sql)) {
          die('Could not query, line ' . __LINE__ . ': ' . mysql_error());
      }
      $sql = "INSERT INTO wordstats (word, word_count, doc_count, word_freq, doc_freq, doc_freq_std) VALUES\n";
      $counter = 0;
    }
  }
  $sql .= " ON DUPLICATE KEY UPDATE word_count=word_count+VALUES(word_count),
                                    doc_count=doc_count+VALUES(doc_count),
                                    word_freq=(word_count+VALUES(word_count))/$word_count,
                                    doc_freq=(doc_freq+VALUES(doc_freq)),
                                    doc_freq_std=(doc_freq_std+VALUES(doc_freq_std))/2;";
  if (!mysql_query($sql)) {
      echo $sql . "\n";
      die('Could not query, line ' . __LINE__ . ': ' . mysql_error());
  }

  $end = time();

  $time = $end - $start;

  mysql_query('
    CREATE TABLE IF NOT EXISTS `docstats` (
      `word_count` bigint(20) unsigned NOT NULL,
      `doc_count` bigint(20) unsigned NOT NULL
    )
  ');
  mysql_query('TRUNCATE TABLE `docstats`;');

  mysql_query('INSERT INTO `docstats` (doc_count,word_count) VALUES ('.$doc_count.','.$word_count.');');

  print 'Total documents: '. $doc_count. '<br />';
  print 'Total words: '. $word_count. '<br />';
  print 'Total time: '. $time .' secs. ('. $doc_count/$time .' documents per sec. )';

