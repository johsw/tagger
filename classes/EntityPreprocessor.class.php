<?php
/**
 * @file
 * Definition of EntityPreprocessor.
 */

require_once __ROOT__ . 'classes/Token.class.php';

/**
 * Finds plausible entities in the text.
 */
class EntityPreprocessor {
  private $tokens;
  private $named_entities;

  /**
   * Constructs EntityPreprocessor object.
   *
   * @param array $tokens
   *   The tokens of a text.
   */
  public function __construct($tokens) {
    $this->tokens = $tokens;
  }

  /**
   * Finds sequences of tokens that are likely to be named entities.
   *
   * It looks for capitalization and non-stopwords.
   *
   * @return array
   *   Plausible named entities.
   */
  public function get_potential_named_entities() {
    if (!isset($this->named_entities)) {
      $this->named_entities = array();
      $this->extract_potential_named_entities();
    }
    return $this->named_entities;
  }

  /**
   * Simply attempts to attract place names or names.
   */
  private function extract_potential_named_entities() {

    for ($i = 0, $n = count($this->tokens); $i < $n; $i++) {
      $entity = NULL;
      $token = $this->tokens[$i];

      // If the token is uppercase, maybe it is a name or a place.
      if ($token->isUpperCase() && !$token->isStopWord() && !$token->isInitWord()) {
        $entity = array(&$this->tokens[$i]);

        /*
         * If the next token is not a punctuation break
         * and it's uppercase or is an infix
         * and it's on the same paragraph
         * then add it to the potential entity
         *
         */

        while (!(isset($this->tokens[$i + 1]) && preg_match('/[!\?,:;\(\)]/u', $this->tokens[$i + 1]->text))
          && isset($this->tokens[$i + 2])
          && ($this->tokens[$i + 2]->isUpperCase() || $this->tokens[$i + 2]->isPrefixOrInfix())
          && $this->tokens[$i + 2]->paragraphNumber == $token->paragraphNumber
        ) {
          $entity[] = &$this->tokens[$i + 2];
          $i += 2;
        }
        $this->named_entities[] = $entity;
        unset($token);
      }
    }
  }
}

