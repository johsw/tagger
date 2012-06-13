<?php

require_once('Tagger.php');
require_once __ROOT__ . 'classes/TaggerInstaller.class.php';

/***
 * 
 * Called by itself this file will create the Tagger database tables.
 * Called with the flag '-j' (json) it will fill the tables
 * with data specified in the json files.
 */

$run_json = FALSE;

// If called from command line
if (php_sapi_name() == 'cli') {
  $cargs = getopt('jf::');

  if (isset($cargs['f'])) {
    $file = $cargs['f'];
    $tagger = Tagger::getTagger(array(), $file);
  }

  if (isset($cargs['j'])) {
    $run_json = TRUE;
  }
}

$tagger = Tagger::getTagger();
$install = new TaggerInstaller($tagger);

if ($run_json) {
  require_once __ROOT__ . 'classes/KeywordImporter.class.php';
  $KI = new KeywordImporter();
  $KI->jsonCreateKeywords(__ROOT__ . 'keywords.json');
  $KI->jsonCreateWordstats(__ROOT__ . 'keyword_texts.json');
  $KI->jsonCreateWordRelations(__ROOT__ . 'keyword_texts.json');
}

