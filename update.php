<?php 

require_once 'defaults.php';
require_once 'conf.php';
require_once 'textminer/Databasebuddy.inc.php';
require_once 'updater/Retriever.class.php';
require_once 'updater/Updater.class.php';

/*
* This file updates the datasets with data from a dataset server 
*
*/
//TODO: Maybe check if file is run from cli or not?!

$retriever = new Retriever();
$result = new Updater($retriever->updates);
