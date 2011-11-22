<?php
/*
 * A token points to a single word or a small number of consecutive words at a
 * specific point in the text
 */
class Token {

  static $initwords;
  static $prefix_infix;
  static $stopwords;

  public $text;

  public $rating = 0;
  public $posRating = 1;
  public $htmlRating = 0;

  public $tokenNumber = NULL;
  public $paragraphNumber = NULL;

  public $hasBeenMarked = FALSE;
  public $tokenParts = FALSE;

  public function __construct($text) {
    $tagger = Tagger::getTagger();
    $language = $tagger->getConfiguration('language');
    $this->text = $text;
    $wordlists = array('initwords', 'prefix_infix', 'stopwords');
    foreach ($wordlists AS $wordlist) {
      if (self::$$wordlist == NULL) {
        $path = realpath(__ROOT__ .'resources/'. $wordlist .'/'. $wordlist .'_'. $language .'.txt');
        self::$$wordlist = file($path, FILE_IGNORE_NEW_LINES);
      }
    }
  }

  public function getText() {
    return $this->text;
  }

  public function isUpperCase() {
    $text = $this->text;
    return $text != mb_strtolower($text, 'UTF-8');
  }

  public function isStopWord() {
    $text = $this->text;
    return in_array(mb_strtolower($text, 'UTF-8'), self::$stopwords);
  }

  public function isInitWord() {
     $text = $this->text;
     return in_array($text, self::$initwords);
  }

  public function isPrefixOrInfix() {
    return in_array(mb_strtolower($this->text), self::$prefix_infix);
  }

  public function __toString() {
    return $this->text;
  }

  public function rateToken($token_count, $paragraph_count, $rating) {
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

    if($this->tokenParts != NULL) {
      foreach ($this->tokenParts as $partial_token) {
        $partial_token->rating = $this->rating;
        $partial_token->htmlRating = $this->posRating;
        $partial_token->posRating = $this->htmlRating;
      }
    }
  }



}
