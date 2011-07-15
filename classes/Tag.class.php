<?php
/*
 * A tag is the overall tags found in the text, i.e. tags are unique.
 * The tag points to the specific places in the text where it was found ($tokens)
 * and has a rating that is the sum of the ratings of the individual tokens.
 */
class Tag extends Token {

  public $tokens;

  public $realName;

  public $freqRating = 0;

  public function __construct($token) {
    if(is_string($token)) {
      parent::__construct($token);
    }
    elseif(is_a($token, 'Token')) {
      $this->text = $token->text;
      /*
      $this->rating = $token->rating;
      $this->freqRating = $token->freqRating;
      $this->posRating = $token->posRating;
      $this->htmlRating = $token->htmlRating;
       */
    }
  }


}


