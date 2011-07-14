<?php

require_once __ROOT__ . 'classes/Tokenizer.class.php';

class HTMLPreprocessor {
  public $html;
  public $paragraphCount;

  public $markTags;
  public $intermediateHTML;
  public $markedHTML;

  private $dom;
  private $named_entities;
  private $tagger;
  private $tagRatings;


  public $tokens;

  public function __construct($text, $mark_tags = FALSE) {
    $this->tagger = Tagger::getTagger();
    $this->tagRatings = $this->tagger->getConfiguration('HTML_tags');

    $this->html = '<?xml encoding="UTF-8">' .  $text;

    $this->markTags = $mark_tags;
  }

  public function parse() {
    $this->dom = new DOMDocument("1.0", "UTF-8");
    $this->dom->loadHTML($this->html);
    $this->rateElement($this->dom, 0);
  }

  /**
   * Recursively rates tokens within an HTML-element
   * and all HTML-elements within it.
   *
   * @param DOMElement $element
   *   Element to be rated.
   * @param integer $cur_rating
   *   The base rating that should be added to the rating of this element.
   *   E.g. if this is a <strong> within an <h4> then the <h4>-rating is added
   *   via $cur_rating to this element's rating.
   *
   */
  public function rateElement($element, $cur_rating, $body_reached = FALSE) {
    if($this->markTags) {
      if($body_reached) {
        $this->makeHTMLbeginTag($element);
      }
      elseif($element->nodeName == 'body') {
        $body_reached = TRUE;
      }
    }

    if($element->nodeName == '#text') {
      $tokenizer = new Tokenizer($element->textContent);
      foreach($tokenizer->tokens as $token) {
        $token->htmlRating = $cur_rating;
        $this->tokens[] = $token;

        if($this->markTags) {
          $this->intermediateHTML[] = &$this->tokens[count($this->tokens)-1];
        }
      }
    }

    if($element->hasChildNodes()) {
      foreach($element->childNodes as $child) {
        if(array_key_exists($child->nodeName, $this->tagRatings)) {
          $this->rateElement($child, $cur_rating + $this->tagRatings[$child->nodeName], $body_reached);
        }
        else {
          $this->rateElement($child, $cur_rating                                      , $body_reached);
        }
      }
    }

    if($this->markTags) {
      if($body_reached && $element->nodeName != 'body') {
        $this->makeHTMLendTag($element);
      }
    }
  }

  private function makeHTMLbeginTag($element) {
  if($element->nodeName == '#text') {
    return;
  }

    $tag = '<' . $element->nodeName;
    if($element->hasAttributes()) {
      foreach($element->attributes as $attribute) {
        $tag .= ' ' . $attribute->nodeName;
        if($attribute->nodeValue != NULL) {
          $tag .= '="' . $attribute->nodeValue . '"';
        }
      }
    }
    $tag .= '>';

    $this->intermediateHTML[] = $tag;
  }

  private function makeHTMLendTag($element) {
    if(!in_array($element->nodeName, array('#text', 'br','img'))) {
      $this->intermediateHTML[] = '</' . $element->nodeName . '>';
    }
  }

}

