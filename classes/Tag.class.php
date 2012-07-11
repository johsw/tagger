<?php
/**
 * @file
 * Definition of Tag.
 */

require_once __ROOT__ . 'classes/Token.class.php';

/*
 * Represents a token.
 *
 * Tags are the overall tags found in the text, i.e. tags are unique.
 * The tag is a collection of identical tokens found at different places in the 
 * text.
 * It has a rating that is the sum of the ratings of the individual tokens.
 */
class Tag extends Token {

  /**
   * The tokens in the text matched by this tag.
   */
  public $tokens = array();

  /**
   * Canonical name of entity (as found in database).
   */
  public $realName;

  /**
   * Names for this tag found in the text.
   */
  public $synonyms = array();

  /**
   * Positional rating of this tag.
   */
  public $posRating = 0;

  /**
   * The frequency rating of this tag.
   */
  public $freqRating = 0;

  /**
   * Whether this tag is a named entity or a keyword.
   */
  public $type = 'named_entity'; // keyword, named_entity

  /**
   * Constructs a Tag object.
   *
   * @param string|Token $token
   *   A string or Token that contains the name of this tag.
   */
  public function __construct($token) {
    if (is_string($token)) {
      parent::__construct($token);
    }
    elseif (is_a($token, 'Token')) {
      parent::__construct($token->text);
    }
    else {
      parent::__construct('');
    }

    if($this->text != '') {
      $this->synonyms[] = $this->text;
    }
  }

  /**
   * Rates the tag.
   *
   * Sums up the ratings of the Tag's Tokens.
   */
  public function rate() {
    foreach ($this->tokens as $synonym_tokens) {
      foreach ($synonym_tokens as $token) {
        $this->freqRating++;
        $this->rating     += $token->rating;
        $this->posRating  += $token->posRating;
        $this->htmlRating += $token->htmlRating;
      }
    }

    $freq_rating = Tagger::getConfiguration($this->type, 'rating', 'frequency');
    $this->rating /= 1 + (($this->freqRating - 1) * (1 - $freq_rating));

    // Normalization
    $this->rating *= 10;
    // Clamp within 0-100%
    $this->rating = max(0, $this->rating);
    $this->rating = min($this->rating, 100);
  }

  public function __toString() {
    $str =  "Tag: $this->realName\n";
    $str .= "Synonyms: " . implode(', ', $this->synonyms) . "\n";
    $str .= "Rating: $this->rating (freq: $this->freqRating, pos: $this->posRating, html: $this->htmlRating)\n";
    return $str;
  }

}

