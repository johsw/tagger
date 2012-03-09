<?php

require_once __ROOT__ . 'classes/Token.class.php';

class Tokenizer {
  public $words;
  public $tokens;

  public function __construct($text, $capture = false) {

    $text = str_replace("\n", " __newline__ ", $text);
    $text = str_replace("\r", " __newline__ ", $text);
    $this->words = $this->split_words($text, $capture);
    foreach($this->words as $word) {
      if($word != '') {
        $this->tokens[] = new Token($word);
      }
    }
  }

  public static $split_regex = "/('s|[\s!\?,\.:;'\"«»\(\)]+)/u";
  public static function split_words($text, $capture = false) {
    if ($capture) {
      return preg_split(self::$split_regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
    else {
      return preg_split(self::$split_regex, $text, -1);
    }
  }
}
