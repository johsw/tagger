<?php
  // Database connectiviy info.
  $conf['db'] = array(
    'name' => '<database name>',
    'server' => '<server>',
    'username' => '<username>',
    'password' => '<password>',
  );
  // Names and ids of your vocabularies.
  $conf['vocab_names'] = array(
    13 => 'personer',
    17 => 'steder',
    15 => 'organisationer',
  );
  // Data sources for open linked data.
  $conf['lod_sources'] = array(
    1 => 'DBpedia',
    2 => 'en.wikipedia.org',
    3 => 'da.wikipedia.org',
    4 => 'GeoNames',
    5 => 'New York Times',
    6 => 'NYT search api'
    );
  // The hostnames of the sites that you would like to be allowed to call the
  // webservice. Leave an empty array if you want to allow access for all.
  $conf['service_allow_referer'] = array('tagger.dk');
?>