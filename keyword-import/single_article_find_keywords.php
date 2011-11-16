<?php


function make_text_array($freq_array, $property) {
  usort($freq_array, function($a,$b) use ($property) { return $a[$property] < $b[$property]; });
  return array_map(function($a) use ($property) { return $a['word'].'<br /><span class="rating">('.$a[$property].')</span>'; }, $freq_array);
}

  require_once('lib_calc_score.php');

  if(isset($_POST['text'])){
    $text = $_POST['text'];
    $frequency = score_text($text);
  }

  if(!isset($_GET['artid'])){
    print "No article id!"; exit;
  } else { 
    $frequency = score_article($_GET['artid']);
  }

  //print_r($frequency);
  $tds[0] = make_text_array($frequency, 'diff');
  $tds[1] = make_text_array($frequency, 'diff_rel');
  $tds[2] = make_text_array($frequency, 'idf');
  $tds[3] = make_text_array($frequency, 'tf-idf');
  $tds[4] = make_text_array($frequency, 'std_rel');
  $tds[5] = make_text_array($frequency, 'std');
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
</head>

<body>
  <form action="" method="POST">
    <textarea name="text">
      <?php echo $text; ?>
    </textarea>
  </form>


  <table>
    <thead>
      <tr>
        <th>Johs</th>
        <th>Johs<br>relativ</th>
        <th>idf</th>
        <th>tf-idf</th>
        <th>Standardafvigelse<br>relativ</th>
        <th>Standardafvigelse<br>absolut</th>
      </tr>
    </thead>
    <tbody>
<?php
  for ($row = 0, $rc = count($tds[0]); $row < $rc; $row++) {
    echo '<tr>';
    for ($column = 0, $cc = count($tds); $column < $cc; $column++) {
      echo '<td>'. $tds[$column][$row] .'</td>';
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
