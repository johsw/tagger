<?php

require_once __ROOT__ . 'classes/Tokenizer.class.php';

class HTMLPreprocessor {
  public $html;
  public $paragraphCount = 0;
  public $tokenCount = 0;

  public $intermediateHTML;

  private $dom;
  private $tagger;


  public $tokens;

  public function __construct($text) {
    $this->tagger = Tagger::getTagger();


    $this->html = '<?xml encoding="UTF-8">' .  $text;

  }

  public function parse() {
    $this->dom = new DOMDocument("1.0", "UTF-8");
    @$this->dom->loadHTML($this->html);
    $this->rateElement($this->dom, 0);
  }

  /**
   * Recursively rates tokens within an HTML-element
   * and all HTML-elements within it.
   * rateElement also builds the intermediateHTML-array, that can be used to
   * recreate the original HTML with inserted markup to emphasize found tags.
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
    // build intermediateHTML
    if (Tagger::getConfiguration('named_entity', 'highlight', 'enable')) {
      if ($body_reached) {
        $this->makeHTMLbeginTag($element);
      }
      elseif ($element->nodeName == 'body') {
        $body_reached = TRUE;
      }
    }

    // check if were in a new paragraph
    if (in_array($element->nodeName, Tagger::getConfiguration('named_entity', 'HTML', 'paragraph_separators')) && trim($element->textContent) != '') {
      $this->paragraphCount++;
    }

    // rate the element if it is a text element
    if ($element->nodeName == '#text') {
      $tokenizer = new Tokenizer($element->textContent, true);
      foreach ($tokenizer->tokens as $token) {
        $token->htmlRating = $cur_rating;
        $token->paragraphNumber = $this->paragraphCount;
        $this->tokenCount++;
        $token->tokenNumber = $this->tokenCount;
        $this->tokens[] = $token;

        if (Tagger::getConfiguration('named_entity', 'highlight', 'enable')) {
          $this->intermediateHTML[] = &$this->tokens[count($this->tokens)-1];
        }
      }
    }

    // recursively rate children
    if ($element->hasChildNodes()) {
      foreach ($element->childNodes as $child) {
        if (array_key_exists($child->nodeName, Tagger::getConfiguration('named_entity', 'HTML', 'tags'))) {
          $tagRatings = Tagger::getConfiguration('named_entity', 'HTML', 'tags');
          $this->rateElement($child, $cur_rating + $tagRatings[$child->nodeName], $body_reached);
        }
        else {
          $this->rateElement($child, $cur_rating, $body_reached);
        }
      }
    }

    // build intermediateHTML
    if (Tagger::getConfiguration('named_entity', 'highlight', 'enable')) {
      if ($body_reached && $element->nodeName != 'body') {
        $this->makeHTMLendTag($element);
      }
    }
  }

  private function makeHTMLbeginTag($element) {
    if ($element->nodeName == '#text') {
      return;
    }

    $tag = '<' . $element->nodeName;
    if ($element->hasAttributes()) {
      foreach ($element->attributes as $attribute) {
        $tag .= ' ' . $attribute->nodeName;
        if ($attribute->nodeValue != NULL) {
          $tag .= '="' . $attribute->nodeValue . '"';
        }
      }
    }
    $tag .= '>';

    $this->intermediateHTML[] = $tag;
  }

  private function makeHTMLendTag($element) {
    if (!in_array($element->nodeName, array('#text', 'br','img'))) {
      $this->intermediateHTML[] = '</' . $element->nodeName . '>';
    }
  }

}

