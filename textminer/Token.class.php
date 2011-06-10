<?php

class Token {
  static $prefix_or_infix = array('bin', 'de', 'du', 'van', 'der', 'von', 'mc', 'mac', 'le', 'for');
  static $stopwords = array('af', 'alle', 'andet', 'andre', 'at', 'begge', 'da', 'de', 'den', 'denne', 'der', 'deres', 'derfor', 'det', 'dette', 'dig', 'din', 'dog', 'du', 'ej', 'eller', 'en', 'end', 'ene', 'eneste', 'enhver', 'et', 'fem', 'fire', 'flere', 'fleste', 'fordi', 'forrige', 'fra', 'få', 'før', 'god', 'han', 'hans', 'har', 'hendes', 'her', 'hun', 'hvad', 'hvem', 'hver', 'hvilken', 'hvis', 'hvor', 'hvordan', 'hvorfor', 'hvornår', 'i', 'ifølge', 'ikke', 'ind', 'ingen', 'intet', 'jeg', 'jeres', 'kan', 'kom', 'kommer', 'lav', 'lidt', 'lille', 'man ', 'mand', 'mange', 'med', 'meget', 'men', 'mens', 'mere', 'mig', 'ned', 'ni', 'nogen', 'noget', 'ny', 'nyt', 'nær', 'næste', 'næsten', 'og', 'også', 'op', 'otte', 'over', 'på', 'se', 'seks', 'ses', 'siden', 'som', 'stor', 'store', 'syv', 'ti', 'til', 'to', 'tre', 'ud', 'var', 'vi');

  static $initwords;

  private $text;

  public function __construct($text) {
    $this->text = trim($text);
    if (NULL == self::$initwords) {
      $initwords_path = realpath(TAGGER_DIR .'/resources/initwords.txt');
      self::$initwords = file($initwords_path, FILE_IGNORE_NEW_LINES);
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
    return in_array(mb_strtolower($this->text), self::$prefix_or_infix);
  }
}
