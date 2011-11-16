<?php

$property = 'diff_outer_doc_freq';
$property_esc = mysql_real_escape_string($property);

function make_text_array($freq_array, $property, $word_count = 1) {
  usort($freq_array, function($a,$b) use ($property) { return $a[$property] < $b[$property]; });
  return array_map(function($a) use ($property, $word_count) { return $a['word'].'<br /><span class="rating">('.$a[$property]/$word_count.')</span>'; }, $freq_array);
}
/*
  if(!isset($_GET['artid'])){
    print "No article id!"; exit;
  }
 */
  $text = '';

  $word_count = 1;
  if(isset($_POST['text'])){
    $text = $_POST['text'];


    require_once('lib_calc_score.php');
    $frequency = score_text($text);
    $tds[] = make_text_array($frequency, 'diff');
    $tds[] = make_text_array($frequency, 'tf-idf');

    $word_count = count($frequency);

    $implode_words = implode('\',\'', array_map('mysql_real_escape_string', array_keys($frequency)));

    $query = 'SELECT * FROM word_relations_'.$property_esc.' WHERE word IN(\''.$implode_words.'\')';
    $result = mysql_query($query);
    if(!$result){
      echo $query;
      print "No words"; exit;
    }

    $subjects = array();

    $unmatched_database = array();
    $unmatched_words = $frequency;
    while ($row = mysql_fetch_object($result)) {
      $key = mb_strtolower($row->word);
      if(array_key_exists($key, $frequency)) {
        unset($unmatched_words[$key]);

        if(!isset($subjects[$row->tid]['rating'])) { $subjects[$row->tid]['rating'] = 0; }
        if(!isset($subjects[$row->tid]['words'])) { $subjects[$row->tid]['words'] = array(); }
        $subjects[$row->tid]['rating'] += $row->score;
        $subjects[$row->tid]['words'][] = array('word' => $row->word, 'rating' => $row->score);

      } else {
        $unmatched_database[] = $key;
      }
    }

    // In the database 'ideen' == 'idéen' but we can't do that in PHP
    // without some cumbersome custom made conversion functions.
    // So instead we loop through the nonmatches and let the database match the
    // indivual words that could be matched by PHP.
    foreach ($unmatched_database as $db_word) {
      foreach ($unmatched_words as $word => $value) {
        if(isset($unmatched_words[$word])) {
          $query = 'SELECT * FROM word_relations_'.$property_esc.' WHERE word = "'.mysql_real_escape_string($db_word).'" AND "'.mysql_real_escape_string($db_word).'" = "'.mysql_real_escape_string($word).'"';
          $result = mysql_query($query);
          if($result === FALSE) {
            die('Could not query, line ' . __LINE__ . ': ' . mysql_error());
          }
          while($row = mysql_fetch_object($result)) {
            unset($unmatched_words[$word]);
            if(!isset($subjects[$row->tid]['rating'])) { $subjects[$row->tid]['rating'] = 0; }
            if(!isset($subjects[$row->tid]['words'])) { $subjects[$row->tid]['words'] = array(); }
            $subjects[$row->tid]['rating'] += $row->score;
            $subjects[$row->tid]['words'][] = array('word' => $row->word, 'rating' => $row->score);
          }
        }
      }
    }

    if(isset($subjects[0])) { unset($subjects[0]); }

    foreach($subjects as $tid => &$value) {
      $query = 'SELECT tid, name FROM term_data WHERE tid = '.$tid;
      $result = mysql_query($query);
      $row = mysql_fetch_object($result);
      // $value['rating'] *= pow(count($value['words']), 2);
      $value['word'] = $row->name;
      $td_keyword_ratings[] = make_text_array($value['words'], 'rating');
    }
    $td_keywords[] = make_text_array($subjects, 'rating', $word_count);
  }

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Tagger-library manual test</title>
  <style>
  .rating { color: gray; }
  </style>
  <!--<link rel="stylesheet" type="text/css" href="reset.css" />-->
  <!--[if IE]>
    <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
  <![endif]-->
</head>

<body>
  <form action="single_article_find_subject.php" method="POST">
    <textarea name="text" rows="20" cols="60">
      <?php echo $text; ?>
    </textarea>
    <input type="submit" value="Send">
  </form>


  <table style="float: right">
    <thead>
      <tr>
        <th>Johs</th>
        <th>tf-idf</th>
      </tr>
    </thead>
    <tbody>
<?php
  if(isset($tds)) {
    for ($row = 0, $rc = count($tds[0]); $row < $rc; $row++) {
      echo '<tr>';
      for ($column = 0, $cc = count($tds); $column < $cc; $column++) {
        echo '<td>'. $tds[$column][$row] .'</td>';
      }
      echo '</tr>';
    }
  }
?>
    </tbody>
  </table>

  <table>
    <thead>
      <tr>
        <th>Keywords</th>
      </tr>
    </thead>
    <tbody>
<?php
  if(isset($td_keywords)) {
    for ($row = 0, $rc = count($td_keywords[0]); $row < $rc; $row++) {
      echo '<tr>';
      for ($column = 0, $cc = count($td_keywords); $column < $cc; $column++) {
        echo '<td>'. $td_keywords[$column][$row] .'</td>';
      }
      echo '</tr>';
    }
  }
?>
    </tbody>
  </table>

  <table>
    <thead>
      <tr>
<?php
  if (isset($subjects)) {
    foreach ($subjects as $value) {
      echo '<th>'.$value['word'].'</th>';
    }
  }
?>
      </tr>
    </thead>
    <tbody>
<?php
  if(isset($td_keyword_ratings)) {
    for ($row = 0, $rc = max(array_map('count', $td_keyword_ratings)); $row < $rc; $row++) {
      echo '<tr>';
      for ($column = 0, $cc = count($td_keyword_ratings); $column < $cc; $column++) {
        if (isset($td_keyword_ratings[$column][$row])) {
          echo '<td>'. $td_keyword_ratings[$column][$row] .'</td>';
        }
        else {
          echo '<td></td>';
        }
      }
      echo '</tr>';
    }
  }
?>
    </tbody>
  </table>


  <br /><br />------------------<br /><br />

  <?php print $text; ?>

</body>
</html>
