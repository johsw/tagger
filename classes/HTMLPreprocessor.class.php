<?php

require_once 'classes/Tokenizer.class.php';
require_once '../wiseparser/treebuilder.php';

class HTMLPreprocessor {
  private $text;
  private $rootElement;
  private $named_entities;

  public $tokens;

  public function __construct($text) {
    $this->text = $text;
  }

  public function parse() {
    $this->rootElement = new Tree();
    $this->rootElement->parse_content($this->text);
    $this->rateElement($this->rootElement, 0);
  }

  public function rateElement($element, $cur_rating) {
    $tagger_instance = Tagger::getTagger();
    $tag_ratings = $tagger_instance->getConfiguration('HTML_tags');

    foreach($element->children as $child) {
      if(is_string($child)) {
        //echo "Child: " . $child . "\n";
        $tokenizer = new Tokenizer($child);
        //echo "Tokens: ";
        //print_r($tokenizer->tokens);
        //echo "\n";
        foreach($tokenizer->tokens as $token) {
          $this->tokens[] = array($token, $cur_rating);
        }
      }
      else {
        $this->rateElement($child, $cur_rating + $tag_ratings[$child->tag]);
      }
    }
  }
}
