<?php
  // Database connectiviy info.
  $tagger_conf['db'] = array(
    'name' => '<database name>',
    'server' => '<server>',
    'username' => '<username>',
    'password' => '<password>',
  );
  $tagger_conf['dbhandler'] = 'Default';

  //Tagger default language - the codes are the same as html language codes (http://www.ietf.org/rfc/rfc1766.txt)
  $tagger_conf['language'] = 'da'; // Others could be sv (Sweden), nn (Norway - Nynorsk) and nb (Norway - Bokmål)

  // Data sources for open linked data.
  $tagger_conf['lod_sources'] = array(
    1 => 'DBpedia',
    2 => 'en.wikipedia.org',
    3 => 'da.wikipedia.org',
    4 => 'GeoNames',
    5 => 'New York Times',
    6 => 'NYT search api'
    );


  $tagger_conf['named_entity']['rating'] = array(
    // Settings for ratings of Named Entities
    // These should be numbers between 0 and 1
    // 0: turned off
    // 0.5: half weight
    // 1: Turned on (full weight)
    'frequency' => 1,
    'HTML' => 1,
    'positional' => 1,

    // the last word or paragraph in the text will have a rating that is 0.3
    // times lower than the first word or paragraph
    'positional_minimum' => 0.3,
    // if the text is shorter than the critical token count, the last word will
    // not be rated as low as the minimum rating
    'positional_critical_token_count' => 350,
  );

  // Keyword-extraction configuration
  // A keyword must be related to at least 15 texts for it to be 
  // processed
  $tagger_conf['keyword']['minimum_number_of_texts'] = 15;
  $tagger_conf['keyword']['property'] = 'diff_outer_doc_freq';
  $tagger_conf['keyword']['enable_stemmer'] = FALSE;
  $tagger_conf['keyword']['normalize'] = FALSE;
  // For a text be given 100% score it must have the equivalent of
  // one full keyword per 100 words
  $tagger_conf['keyword']['max_score'] = 1;
  // For a text to be identified with a keyword
  // it must have the equivalent of one full keyword per 250 words
  $tagger_conf['keyword']['threshold'] = 1/250;

  $tagger_conf['keyword']['debug'] = FALSE;

  // Disambiguation
  $tagger_conf['disambiguate'] = TRUE;

  // Logging of possible NE's that weren't found in database.
  $tagger_conf['log_unmatched'] = FALSE;

  // Find URI to Wikipedia etc. for tags
  $tagger_conf['return_uris'] = FALSE;

  // Highlighting of tags
  $tagger_conf['highlight'] = array(
    'enable' => FALSE, // on/off toggle of tag-highlighting
    'start_tag' => '<strong>',
    'end_tag' => '</strong>',
    'substitution' => FALSE,
  );

  // HTML rating
  $tagger_conf['HTML'] = array(
    // how the content of tags are rated
    'tags' => array(
      'h1' => 10,
      'h2' => 7,
      'h3' => 5,
      'strong' => 3,
      '#text' => 1,
    ),
    // which tags define/separate a paragrah
    'paragraph_separators' => array(
      'p',
      'h1',
      'h2',
      'h3',
    ),
  );

  // Settings for logging
  $tagger_conf['log'] = array(
    'handler' => 'Default',
    'type' => 'file',
    'level' => 'standard', // none error warning standard verbose
  );

  // Database table names
  $tagger_conf['db']['lookup_table'] = 'tagger_lookup';
  $tagger_conf['db']['linked_data_table'] = 'tagger_linked_data_sources';
  $tagger_conf['db']['disambiguation_table'] = 'tagger_disambiguation';
  $tagger_conf['db']['unmatched_table'] = 'tagger_unmatched';

  $stemmer_postfix = ($tagger_conf['keyword']['enable_stemmer'])? '_stem' : '';

  $tagger_conf['db']['docstats_table'] = 'tagger_docstats';
  $tagger_conf['db']['wordstats_table'] = 'tagger_wordstats' . $stemmer_postfix;
  $tagger_conf['db']['word_relations_table'] = 'tagger_word_relations_' . $tagger_conf['keyword']['property'] . $stemmer_postfix;


  // Defaults
  $tagger_conf['ner_vocab_ids'] = array();
  $tagger_conf['keyword_vocab_ids'] = array();
