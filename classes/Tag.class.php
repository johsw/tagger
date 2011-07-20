<?php

require_once __ROOT__ . 'classes/Token.class.php';

/*
 * Tags are the overall tags found in the text, i.e. tags are unique.
 * The tag points to the specific places in the text where it was found ($tokens)
 * and has a rating that is the sum of the ratings of the individual tokens.
 */
class Tag extends Token {

  public $tokens = array();

  public $realName;
  public $synonyms = array();

  public $posRating = 0;
  public $freqRating = 0;

  public function __construct($token) {
    if (is_string($token)) {
      parent::__construct($token);
    }
    elseif (is_a($token, 'Token')) {
      $this->text = $token->text;
    }
    if($this->text != '') {
      $this->synonyms[] = $this->text;
    }
  }

  public static function mergeTags($tags, $real_name = '') {
    $ret_tag = new Tag($real_name);
    $ret_tag->realName = $real_name;
    foreach ($tags as $tag) {
      $ret_tag->synonyms    = array_unique(array_merge($ret_tag->synonyms, $tag->synonyms));
      $ret_tag->rating     += $tag->rating;
      $ret_tag->freqRating += $tag->freqRating;
      $ret_tag->posRating  += $tag->posRating;
      $ret_tag->htmlRating += $tag->htmlRating;
      $ret_tag->tokens      = array_merge_recursive($ret_tag->tokens, $tag->tokens);
    }
    return $ret_tag;
  }


}


