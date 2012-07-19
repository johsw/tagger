<?php
/**
 * @file
 * Definition of PlainTextPreprocessor.
 */

require_once __ROOT__ . 'classes/Tokenizer.class.php';

/**
 * Preprocessor for plain text (non-HTML).
 *
 * Counts words and paragraphs in the text.
 *
 * @see HTMLPreprocessor
 */

class PlainTextPreprocessor {

  /**
   * The text to be preprocessed.
   */
  private $text;

  /**
   * Number of paragraphs in the text.
   */
  public $paragraphCount = 0;

  /**
   * Number of tokens in the text.
   */
  public $tokenCount = 0;

  /**
   * Tokens in the text.
   */
  public $tokens;

  /**
   * Structure for highlighting.
   */
  public $intermediateHTML;



  /**
   * Constructs the PlainTextPreprocessor
   *
   * @param string $text
   *   The text to be preprocessed.
   */
  public function __construct($text) {
    $this->tagger = Tagger::getTagger();

    if (Tagger::getConfiguration('br2nl')) {
      $text = strtr($text, "\r", '');
      $text = strtr($text, "\n", '');
      $text = $this->br2nl($text);
    }
    $this->text = trim($text);

  }


  /**
   * The preprocessing function.
   *
   * Tokenizes the text and assigns paragraph numbers to the tokens.
   */
  public function parse() {
    $tokenizer = new Tokenizer($this->text, true);
    $this->tokens = $tokenizer->tokens;
    $this->tokenCount = count($this->tokens);

    $this->paragraphCount = 1;
    $curTokenCount = 0;
    $lookingForWord = FALSE;

    foreach ($this->tokens as &$token) {
      // a newline followed by a word (at some point) denotes a new paragraph
      // i.e. two newlines in a row with whitespace in between only gives one
      // new paragraph (assuming they're followed by an actual word)
      if ($token->text == '__newline__') {
        $lookingForWord = TRUE;
      }
      else if ($lookingForWord && trim($token->text) != '') {
        $this->paragraphCount++;
        $lookingForWord = FALSE;
      }
      $token->tokenNumber = ++$curTokenCount;
      $token->paragraphNumber = $this->paragraphCount;

    }

    if (Tagger::getConfiguration('named_entity', 'highlight', 'enable')) {
      $this->intermediateHTML = &$this->tokens;
    }
  }

  /**
   * Convert BR tags to nl
   * from http://php.net/manual/en/function.nl2br.php
   *
   * @param string The string to convert
   * @return string The converted string
   */
  private function br2nl($string) {
      return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
  }

}

