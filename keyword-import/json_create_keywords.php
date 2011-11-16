<?php
/* This file creates the relations between subjects and words in
   the database */

if (count($argv) > 2) {
  file_put_contents('php://stderr', 'Error: Zero or one command-line argument should be given.');
  return 1;
}

require_once('lib_keyword.php');
if (count($argv) == 2) {
  multiple_keywords_create_from_json($argv[1]);
}
else {
  multiple_keywords_create_from_json();
}
