<?php
require_once __ROOT__ . 'classes/Matcher.class.php';

require_once __ROOT__ . 'classes/Token.class.php';
require_once __ROOT__ . 'classes/Tag.class.php';
require_once __ROOT__ . 'classes/EntityPreprocessor.class.php';

class NamedEntityMatcher extends Matcher {

  private $partialTokens;

  function __construct($partial_tokens) {

    $this->partialTokens = $partial_tokens;

    $entityPreprocessor = new EntityPreprocessor($this->partialTokens);
    $potential_entities = $entityPreprocessor->get_potential_named_entities();
    TaggerLogManager::logDebug("Found potential entities:\n" . print_r($potential_entities, TRUE));

    $potential_entities = TagProcessor::mergeTokens($potential_entities);
    TaggerLogManager::logDebug("Merged:\n" . print_r($potential_entities, TRUE));

    parent::__construct($potential_entities);
  }

  public function match() {
    // We search for all tags 'straight up'.
    $this->term_query();
    // But maybe some tags were genitives, e.g. 'Rod Stewart's toys'
    // - a danish genitive ends in 's'.
    $nonmatches = array_filter($this->nonmatches, create_function('$token', 'return (substr($token->text, -1) != "s");'));
    $possible_genitives = array_filter($this->nonmatches, create_function('$token', 'return (substr($token->text, -1) == "s");'));
    TaggerLogManager::logDebug("Possible genitives:\n" . print_r($possible_genitives, TRUE));

    // We do a new search for the possibly unmatched genitives.
    if (!empty($possible_genitives)) {
      foreach ($possible_genitives as &$ends_with_s) {
        $ends_with_s->text = mb_strtolower(rtrim($ends_with_s->text, 's'));
      }
      $this->setTokens($possible_genitives);
      $this->term_query();
    }

    //Merge the nonmatches from the first query with nonmatches from the genetive query
    $this->nonmatches = array_merge($nonmatches, $this->nonmatches);

    return;
  }

}
