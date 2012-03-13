<?php

require_once('Tagger.php');
require_once __ROOT__ . 'classes/TaggerInstaller.class.php';

$tagger = Tagger::getTagger();

$install = new TaggerInstaller($tagger);