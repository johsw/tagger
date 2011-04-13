<?php

require_once 'Token.class.php';
require_once 'NamedEntityMatcher.class.php';

class EntityPreprocessor {
  private $tokens;
  private $named_entities;

  private $ner_matcher;
  private $ner_vocabs;

  public function __construct($text, $ner_vocabs) {
    $text = str_replace("\n", " __newline__ ", $text);
    $text = str_replace("\r", " __newline__ ", $text);
    $this->tokens = preg_split("/([\s\?,\":\.«»'\(\)\!])/", trim($text), -1, PREG_SPLIT_DELIM_CAPTURE);
    $this->ner_vocabs = $ner_vocabs;
  }

  public function get_potential_named_entities() {
    if (!isset($this->named_entities)) {
      $this->named_entities = array();
      $this->extract_potential_named_entities();
    }
    return $this->named_entities;
  }

  public function get_named_entity_matched_tags() {
    // Make sure the named entities have been created.
    $this->get_potential_named_entities();

    $this->ner_matcher = new NamedEntityMatcher($this->named_entities, $this->ner_vocabs);
    $this->ner_matcher->match();
    return $this->ner_matcher->get_matches();
  }
  public function get_nonmatches() {

    return $this->ner_matcher->get_nonmatches();
  }

  /**
   * Simply attempts to attract place names or names.
   */
  private function extract_potential_named_entities() {
    for ($i = 0, $n = count($this->tokens); $i < $n; $i++) {
      $entity = NULL;
      $token = new Token($this->tokens[$i]);
      // If the token is uppercase, maybe it is a name or a place.

      if ($token->isUpperCase() && !$token->isStopWord() && !$token->isInitWord()) {

        $entity = array($token->getText());

        // Look two words ahead.
        if (isset($this->tokens[$i +2])) {
        $next = new Token($this->tokens[$i +2]);
          while (($next->isUpperCase() || $next->isPrefixOrInfix())) {
            // Jump two words.
            $i += 2;
            $entity[] = $next->getText();
            $next = new Token($this->tokens[$i+2]);
          }
        }
        $this->named_entities[] = $entity;
      }
    }
  }
}
