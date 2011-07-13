<?php
  // Database connectiviy info.
  $tagger_conf['db'] = array(
    'name' => '<database name>',
    'server' => '<server>',
    'username' => '<username>',
    'password' => '<password>',
  );
  $tagger_conf['dbhandler'] = 'Default';
  // Data sources for open linked data.
  $tagger_conf['lod_sources'] = array(
    1 => 'DBpedia',
    2 => 'en.wikipedia.org',
    3 => 'da.wikipedia.org',
    4 => 'GeoNames',
    5 => 'New York Times',
    6 => 'NYT search api'
    );
  // The hostnames of the sites that you would like to be allowed to call the
  // webservice. Leave an empty array if you want to allow access for all.
  $tagger_conf['service_allow_referer'] = array('tagger.dk');


  // Settings for ratings of Named Entities
  // These should be numbers between 0 and 1
  // 0: turned off
  // 0.5: half weight
  // 1: Turned on (full weight)
  $tagger_conf['positional_rating'] = 1;
  $tagger_conf['frequency_rating'] = 1;
  $tagger_conf['HTML_rating'] = 1;

  // HTML rating
  $tagger_conf['HTML_tags'] = array(
    'h1' => 10,
    'h2' => 7,
    'strong' => 5,
    // text that is not within any of the HTML-tags above has a rating of 1
    // i.e. plain text is rated with 1
  );

  // Settings for logging
  $tagger_conf['log_handler'] = 'Default';
  $tagger_conf['logging_type'] = 'file'; // file db
  $tagger_conf['logging_level'] = 'standard'; // none error warning standard verbose



