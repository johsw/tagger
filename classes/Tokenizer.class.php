<?php
/**
 * @file
 * Contains Tokenizer.
 */

require_once __ROOT__ . 'classes/Token.class.php';

/**
 * Implements tokenizing functionality.
 *
 * This class contains function to split texts into tokens.
 */
class Tokenizer {
  /**
   * When the text is split via the constructor of Tokenizer, the `Token`s are
   * saved in this array.
   */
  public $tokens;

  /**
   * This regex defines the delimiters at which the text is split.
   *
   * The text is split at whichever places in the text this regex matches.
   * The tokens are whatever is between these delimiters (spaces,
   * commas, quotes etc.)
   */
  public static $split_regex = "/('s|[\s!\?,\.:;'\"«»\(\)]+)/u";

  /**
   * Splits a text into `Token`s
   *
   * @param string $text
   *   The text to be split.
   * @param bool $capture
   *   Whether delimiters (comma, period, colon etc.) should be returned along
   *   with the tokens.
   */
  public function __construct($text, $capture = FALSE) {

    $text = str_replace("\n", " __newline__ ", $text);
    $text = str_replace("\r", " __newline__ ", $text);
    $words = $this->split_words($text, $capture);
    foreach($words as $word) {
      if($word != '') {
        $this->tokens[] = new Token($word);
      }
    }
  }

  /**
   * The splitting function.
   *
   * Uses `preg_split` and `$split_regex` to split a text into words.
   *
   * @param string $text
   *   The text to be split
   * @param bool $capture
   *   Whether delimiters (comma, period, colon etc.) should be returned along
   *   with the tokens.
   *
   * @return array
   *   An array of strings which are the tokens and possibly
   *   (depending on $capture) the delimiters.
   */
  public static function split_words($text, $capture = FALSE) {
    if ($capture) {
      return preg_split(self::$split_regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
    else {
      return preg_split(self::$split_regex, $text, -1);
    }
  }
}
