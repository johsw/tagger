<?php

require_once 'classes/Token.class.php';
require_once 'classes/NamedEntityMatcher.class.php';

class Tokenizer {
  public $tokens;

  public function __construct($text) {
    $text = str_replace("\n", " __newline__ ", $text);
    $text = str_replace("\r", " __newline__ ", $text);
    $this->tokens = preg_split("/([\s\?,\":\.«»'\(\)\!])/", trim($text), -1, PREG_SPLIT_DELIM_CAPTURE);
  }
}
