<?php

require_once __ROOT__ . 'classes/Tokenizer.class.php';

class HTMLPreprocessor {
  private $text;
  private $html;
  private $dom;
  private $named_entities;

  public $tokens;

  public function __construct($text) {
    $this->text = $text;
    $this->html = '<?xml encoding="UTF-8">' .  $text;
  }

  public function parse() {
    $this->dom = new DOMDocument("1.0", "UTF-8");
    $this->dom->loadHTML($this->html);
    $this->rateElement($this->dom, 0);
  }

  public function rateElement($element, $cur_rating) {
    $tagger_instance = Tagger::getTagger();
    $tag_ratings = $tagger_instance->getConfiguration('HTML_tags');

    if($element->nodeName == '#text') {
      $tokenizer = new Tokenizer($element->textContent);
      foreach($tokenizer->tokens as $token) {
        $token->htmlRating = $cur_rating;
        $this->tokens[] = $token;
      }
    }

    if($element->hasChildNodes()) {
      foreach($element->childNodes as $child) {
        $this->rateElement($child, $cur_rating + $tag_ratings[$child->nodeName]);
      }
    }
  }
}
