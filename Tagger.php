<?php
define('__ROOT__', dirname(__FILE__) . '/');
mb_internal_encoding('UTF-8');
require_once __ROOT__ . 'classes/TaggedText.class.php';

class Tagger {

  private static $instance;

  private static $conf_settings;

  private static $configuration;


  private function __construct($configuration = array())  {
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
    define('TAGGER_DIR', dirname(__FILE__));
    include 'defaults.php';
    $tagger_conf = array_merge($tagger_conf, $configuration);
    if (!isset($configuration) || empty($configuration)) {
      include 'conf.php';
    }
    self::$configuration = $tagger_conf;

  }

  public static function getTagger($configuration = array()) {
    if (!isset(self::$instance)) {
        $c = __CLASS__;
        self::$instance = new $c($configuration);
    }
    return self::$instance;
  }

  // getConfiguration('keyword', 'stemmer') ==
  //   $this->configuration['keyword']['stemmer']
  public static function getConfiguration() {
    $arg_count = func_num_args();
    if ($arg_count = 0) {
      return self::$configuration;
    }
    else {
      $opt = self::$configuration;
      $setting_str = '$configuration';
      foreach(func_get_args() as $arg) {
        $setting_str .= "['$arg']";
        if (isset($opt[$arg])) {
          $opt = $opt[$arg];
        }
        else {
          throw new ErrorException('Setting ' . $setting_str . ' not found in configuration.');
          //return FALSE;
        }
      }
      return $opt;
    }
  }

  public static function setConfiguration() {
    $arg_count = func_num_args();
    $args = func_get_args();
    if (is_array($args[0])) {
      $tagger_conf = array_merge(self::$configuration, $args[0]);
      self::$configuration = $tagger_conf;
      return $tagger_conf;
    }
    if ($arg_count < 2) {
      throw new ErrorException('Need at least two arguments.');
    }
    $opt =& self::$configuration;
    $l = array_slice(func_get_args(), 1);
    print_r($l);
    $setting_str = '$configuration';
    foreach($l as $arg) {
      $setting_str .= "['$arg']";
      if (is_array($opt) && isset($opt[$arg])) {
        $opt =& $opt[$arg];
      }
      else {
        throw new ErrorException('Setting ' . $setting_str . ' not found in configuration.');
      }
    }
    $opt = func_get_arg(0);
    return $opt;
  }

  // Prevent users to clone the instance
  public function __clone() {
    trigger_error('Clone is not allowed.', E_USER_ERROR);
  }

  /**
   * This is the main function to call, when you use Tagger.
   *
   * @param $text
   *   The text you want to tag.
   * @param array $options
   *   An associative array of additional options, with the following elements:
   *   - 'ner_vocab_ids': An numeric array vocabularies you want to use for
   *     NER (named entity recognition). Keys are vocabulary ids. Values are
   *     vocabulary names.
   *   - 'keyword_vocab_ids': An numeric array vocabularies you want to use for
   *     Keyword Extraction´. Keys are vocabulary ids. Values are vocabulary names.
   *   - 'rate_html': Boolean indication wheter html-tags should be used to rate
   *     relevancy.
   *   - 'return_marked_text': Boolean, indicates whether Tagger should return
   *     text with markup.
   *   - 'rating': An array TODO: explain array
   *   - 'disambiguate': Boolean indicating whether Tagger should try to disambiguate
   *     ambigous tags.
   *   - 'return_uris': Boolean indicating wheter Tagger should return URI's for
   *     each tag
   *   - 'log_unmatched': Boolean indicating whether unmatched potential
   *     NER candidates should be logged
   *   - 'nl2br': Boolean indicating whether newlines should be convertet to br-tags
   * @return
   *   An HTML string containing a link to the given path.
   */

  public function tagText($text, $options) {
    if (empty($options)) {
      $options = array();
    }

    // let $options array override $configuration (i.e. conf.php and defaults.php)
    foreach(self::$configuration as $key => $value) {
      if (!isset($options[$key])) {
        $options[$key] = $value;
      }
      else {
        if(is_array($options[$key])) {
          if ($key == 'ner_vocab_ids' || $key == 'keyword_vocab_ids') {
            // we allow empty arrays here because that is how
            // you disable NER or Keyword Extraction
            continue;
          }
          else {
            $options[$key] = array_merge_recursive_simple(self::$configuration[$key], $options[$key]);
          }
        }
      }
    }

    if (empty($options['ner_vocab_ids']) && empty($options['keyword_vocab_ids'])) {
      throw new ErrorException('Missing vocab definition in configuration.');
    }

    $tagged_text = new TaggedText($text, $options);
    $tagged_text->process();
    return $tagged_text;
  }
}


/*
Taken from:
http://php.net/manual/en/function.array-merge-recursive.php
walf 26-May-2011 05:23

Recursively merges arrays, but duplicate string keys are overwritten
if the value is not an array.

$merge = array_merge_recursive_simple($a1, $a2, $a3 ...)
The values of $a3 overwrites those of $a1 and $a2 assuming they have
duplicate keys. (string keys)
e.g. $a1["b"] = "foo"
     $a2["b"] = "bar"
gives
$merge["b"] === "bar"

Values with numeric keys are simply appended - the key is NOT preserved.
e.g. $a1[4] = "foo"
     $a2[4] = "bar"
gives
$merge[0] === "foo"
$merge[1] === "bar"
*/
function array_merge_recursive_simple() {
  if (func_num_args() < 2) {
    trigger_error(__FUNCTION__ .' needs two or more array arguments', E_USER_WARNING);
    return;
  }
  $arrays = func_get_args();
  $merged = array();
  while ($arrays) {
    $array = array_shift($arrays);
    if (!is_array($array)) {
      trigger_error(__FUNCTION__ .' encountered a non array argument', E_USER_WARNING);
      return;
    }
    if (!$array) {
      continue;
    }
    foreach ($array as $key => $value) {
      if (is_string($key)) {
        if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key])) {
          $merged[$key] = call_user_func(__FUNCTION__, $merged[$key], $value);
        } else {
          $merged[$key] = $value;
        }
      } else {
        $merged[] = $value;
      }
    }
  }
  return $merged;
}