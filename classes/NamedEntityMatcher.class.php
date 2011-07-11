<?php

require_once 'classes/Matcher.class.php';

class NamedEntityMatcher extends Matcher {

  private $possible_genitives;

  function __construct($text, $ner_vocabs) {
    parent::__construct($text, $ner_vocabs);
    //echo "\n NER-MATCHER \n";
    //print_r($text);
  }

  public function match() {
    $this->term_query();
    return;
    /*
    // First we do the query with the unedited words.
    $this->term_query();
    print_r($this_matches);
    // Get the alrady matched words out of the candidate list.
    foreach ($this->matches as $vocab) {
      foreach ($vocab as $plural_match) {
        $key = array_search($plural_match['word'], $this->search_items);
        if ($key) {
          unset($this->search_items[$key]);
          unset($this->possible_genitives[$key]);
        }
        $key = FALSE;
      }
     
    }


    // See if some of the genitives match straight up.
    $this->match_genitives();
    foreach ($this->possible_genitives as &$item) {
      $item = rtrim($item, 's');
    }
    // Get rid of the plurals in the search candidates.
    $this->search_items = array_merge($this->search_items, $this->possible_genitives);
     */
  }

  private function match_genitives() {

    $this->possible_genitives = array_filter($this->search_items, create_function('$str', 'return (substr($str, -1) == "s");'));
    $this->term_query($this->possible_genitives);
  }
}
