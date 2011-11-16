<?php
  require_once('lib_keyword.php');

  if(isset($_GET['n'])){
    $start = $_GET['n'];
    $stop = $_GET['n']+1;
  } else {
    $start = 0;
    $stop = 1;
  }
  $query = "SELECT tid, name FROM term_data WHERE vid = 16 LIMIT $start,$stop";
  if(isset($_GET['tid'])){
    $query = "SELECT tid, name FROM term_data WHERE vid = 16 AND tid = $_GET[tid]";
  }

  echo $query . "<br>\n";

  $result = TaggerQueryManager::query($query);
  $row = TaggerQueryManager::fetch($result);

  // get articles with that subject in the title
  $keyword_subject = $row['name'];
  $keyword_subject_id = $row['tid'];
  $keyword_subject_esc = mysql_real_escape_string($keyword_subject);

  $normalize = false;
  if (isset($_GET['normalize']) && $_GET['normalize'] == 'true') {
    $normalize = true;
  }

  $range = '';
  if (isset($_GET['fulltext'])) {
    $range = 'fulltext';
  }
  if (isset($_GET['underrubrik'])) {
    $range = 'underrubrik';
  }
  if (isset($_GET['tagged'])) {
    $range = 'tagged';
  }
  $result = subject_name_related_words($keyword_subject, 'all', $range, $normalize);

  $doc_count = $result['doc_count'];
  $doc_ids = $result['doc_ids'];
  $freq_array = $result['freq_array'];

  function make_text_array($freq_array, $property) {
    $test = current($freq_array);
    if(!isset($test[$property])) { return false; }
    // sort the related words by score
    usort($freq_array, function($a,$b) use ($property) { return $a[$property] < $b[$property]; });
    return array_map(function($a) use ($property) { return $a['word'].'<br /><span class="rating">('.$a[$property].')</span>'; }, $freq_array);
  }

  //print_r($frequency);
  $tds[] = make_text_array($freq_array, 'diff');
  //$tds[] = make_text_array(array_filter($freq_array, function($value) use ($doc_count) { return $value['doc_count'] > $doc_count/5; }), 'diff');
  $tds[] = make_text_array($freq_array, 'diff_outer_doc_freq');
  $tds[] = make_text_array($freq_array, 'diff_outer_doc_freq_log');
  //$tds[] = make_text_array(array_filter($freq_array, function($value) use ($doc_count) { return $value['doc_count'] > $doc_count/5; }), 'diff_malt_lin');
  //$tds[] = make_text_array($freq_array, 'diff_malt_x2');
  //$tds[] = make_text_array($freq_array, 'diff_malt_x2_log');
  //$tds[] = make_text_array($freq_array, 'diff_malt_log');
  //$tds[] = make_text_array($freq_array, 'diff_malt_x2_idf');
  //$tds[] = make_text_array(array_filter($freq_array, function($value) use ($doc_count) { return $value['doc_count'] > $doc_count/5; }), 'diff_malt_x2');
  $tds[] = make_text_array($freq_array, 'outer_doc_freq');
  $tds[] = make_text_array($freq_array, 'inner_doc_freq');
  $tds[] = make_text_array($freq_array, 'doc_freq');
  //$tds[] = make_text_array($freq_array, 'malt_x2');
  $tds[] = make_text_array($freq_array, 'doc_count');
  $tds[] = make_text_array($freq_array, 'doc_count_db');
  //$tds[] = make_text_array($freq_array, 'tf-idf');
  //$tds[] = make_text_array(array_filter($freq_array, function($value) use ($doc_count) { return $value['doc_count'] > $doc_count/5; }), 'tf-idf');
  //$tds[] = make_text_array($freq_array, 'tf-idf_malt_lin');
  //$tds[] = make_text_array($freq_array, 'tf-idf_malt_x2');
  //print_r($tds);

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
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js">
  </script>
  <script>
  $(document).ready(function(){
    //you might want to be a bit more specific than only 'td', maybe 'table.classname td' or 'table#id td'
    $('td').click(function(){
      var $this = $(this);
      //find the index of the clicked cell in the row
      var index = $this.prevAll().length;
      //go back to the parent table (here you might also want to use the more specific selector as above)
      //and in each row of that table...
      $this.parents('table').find('tr').each(function(){
        //...highlight the indexth cell
        if($(this).find('td:eq('+index+')').css('background-color') != 'yellow') {
          $(this).find('td:eq('+index+')').css('background-color', 'yellow')
        } else {
          $(this).find('td:eq('+index+')').css('background-color', 'none')
        }
      });
    });
  });
  </script>

</head>

<body>
  <h3>Emne: <?php echo $keyword_subject; ?></h3>
  <h4>ID: <?php echo $keyword_subject_id; ?></h4>
  <p>Antal fundne artikler: <?php echo $doc_count; ?></p>
  <p>(<?php if (!empty($doc_ids)) { echo implode(',', $doc_ids); } ?>)</p>

  <table>
    <thead>
      <tr>
        <th>Johs</th>
        <!--<th>Johs<br>20% grænse</th>-->
        <th>Johs<br>outer_doc_freq</th>
        <!--<th>Johs<br>Malthe-lineær<br>20% grænse</th>-->
        <th>log(Johs<br>outer_doc_freq)</th>
        <!--<th>Johs<br>Malthe-x<sup>2</sup></th>-->
        <!--<th>log8(10000/Johs<br>Malthe-x<sup>2</sup>)</th>-->
        <!--<th>Johs<br>Malthe-log</th>-->
        <!--<th>Johs<br>Malthe-x<sup>2</sup><br>idf</th>
        <th>Johs<br>Malthe-x<sup>2</sup><br>20% grænse</th>-->
        <th>outer_doc_freq</th>
        <th>inner_doc_freq</th>
        <th>doc_freq</th>
        <!--<th>Malthe-x<sup>2</sup></th>-->
        <th>Optræder i x artikler<br>relateret til dette emne</th>
        <th>Optræder i x artikler</th>
        <!--<th>tf-idf</th>
        <th>tf-idf<br>20%</th>
        <th>tf-idf<br>Malthe-lineær</th>
        <th>tf-idf<br>Malthe-x<sup>2</sup></th>-->
        <!--<th>Gennemsnit</th>-->
      </tr>
    </thead>
    <tbody>
<?php
  for ($row = 0, $rc = count($tds[0]); $row < $rc; $row++) {
    echo '<tr>';
    for ($column = 0, $cc = count($tds); $column < $cc; $column++) {
      if(isset($tds[$column][$row])) {
        echo '<td>'. $tds[$column][$row] .'</td>';
      }
      else {
        echo '<td></td>';
      }
    }
    echo '</tr>';
  }
?>
    </tbody>
  </table>

  <br /><br />------------------<br /><br />

  <?php print $text; ?>

</body>
</html>
