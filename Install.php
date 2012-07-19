<?php
/**
 * @file
 * Installer script.
 *
 * Called by itself this file will create the Tagger database tables.
 * Called with the flag '-j' (json) it will fill the tables
 * with data specified in the json files.
 */

require_once('Tagger.php');
require_once __ROOT__ . 'classes/TaggerInstaller.class.php';


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
  require_once __ROOT__ . 'classes/JSONKeywordImporter.class.php';
  $KI = new JSONKeywordImporter();
  $KI->createKeywords('keywords.json');
  $KI->createWordstats('keyword_texts.json');
  $KI->createWordRelations('keyword_texts.json');
}

