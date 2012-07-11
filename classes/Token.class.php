<?php
/**
 * @file
 * Definition of Token.
 */


/*
 * Represents a token.
 *
 * A single word or a small number of consecutive words.
 */
class Token {


  /**
   * The text contained in the token.
   */
  public $text;

  /**
   * The text contained in the token. (lowercase)
   */
  public $text_lowercase;

  /**
   * Tokens that form this token.
   */
  public $tokenParts = array();

  /**
   * The total rating of this token.
   */
  public $rating = 0;

  /**
   * The positional rating of this token.
   */
  public $posRating = 1;


  /**
   * The HTML rating of this token.
   */
  public $htmlRating = 0;

  /**
   * The token number of this token in the text.
   */
  public $tokenNumber = NULL;

  /**
   * The paragraph which this token is in.
   */
  public $paragraphNumber = NULL;

  /**
   * Whether the tag has been modified for highlighting.
   */
  public $hasBeenHighlighted = FALSE;


  /**
   * Construct a token object.
   *
   * @param string $text
   *   The text of the token.
   */
  public function __construct($text) {
    $this->text = $text;
    $this->text_lowercase = mb_strtolower($this->text, 'UTF-8');
  }

  /**
   * Whether this token is uppercase.
   *
   * @return bool
   *   TRUE if the token is uppercase.
   */
  public function isUpperCase() {
    return $this->text != $this->text_lowercase;
  }

  /**
   * Whether this token is a stopword.
   *
   * @return bool
   *   TRUE if the token is a stopword.
   */
  public function isStopWord() {
    return isset(Tagger::$stopwords[$this->text_lowercase]);
  }

  /**
   * Whether this token is an initword.
   *
   * @return bool
   *   TRUE if the token is an init word.
   */
  public function isInitWord() {
    return isset(Tagger::$initwords[$this->text_lowercase]);
  }

  /**
   * Whether this token is an infix or prefix word.
   *
   * @return bool
   *   TRUE if the token is an infix or prefix word.
   */
  public function isPrefixOrInfix() {
    return isset(Tagger::$prefix_infix[$this->text_lowercase]);
  }

  /**
   * Whether this token is an infix or prefix word.
   *
   * @return string
   *   The text of the Token ($text).
   */
  public function __toString() {
    return $this->text;
  }

  /**
   * Rates the token.
   *
   * A product of the positional and HTML rating of this token.
   *
   * @param int $token_count
   *   The total number of tokens in the text.
   * @param int paragraph_count
   *   The total number of paragraphs in the text.
   */
  public function rateToken($token_count, $paragraph_count) {
    $rating = Tagger::getConfiguration('named_entity', 'rating');
    $min_pos_rating = (1 - $rating['positional_minimum']) * exp(-$token_count/$rating['positional_critical_token_count']) + $rating['positional_minimum'];
    $a = log(1 - (1 - $min_pos_rating) * $rating['positional']);

    if ($paragraph_count >= 3) {
      //$this->posRating = exp($a * (($this->paragraphNumber - 1) / ($paragraph_count - 1)));
      $this->posRating = exp($a * (($this->paragraphNumber - 1) / ($paragraph_count + 1)));
    }
    else {
      //$this->posRating = exp($a * (($this->tokenNumber - 1) / ($token_count - 1)));
      $this->posRating = exp($a * (($this->tokenNumber - 1) / ($token_count + 1)));
    }

    // ATTENTION: this is the rating expression for single tokens!
    $this->rating = (1 + $this->htmlRating * $rating['HTML']) * $this->posRating;

    foreach ($this->tokenParts as $partial_token) {
      $partial_token->rating = $this->rating;
      $partial_token->htmlRating = $this->posRating;
      $partial_token->posRating = $this->htmlRating;
    }
  }

}

