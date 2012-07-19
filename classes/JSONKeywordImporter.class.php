<?php
/**
 * @file
 * Contains JSONKeywordImporter.
 */

require_once __ROOT__ . 'classes/KeywordImporter.class.php';

/**
 * KeywordImporter for JSON data.
 */
class JSONKeywordImporter extends KeywordImporter {

  // The parent constructor is implicitly called.

  /**
   * Create the keywords table from a JSON file.
   *
   * @param string $filename
   *   The JSON file that contains the list of keywords.
   */
  public function createKeywords($filename = 'keywords.json') {
    $json = $this->jsonLoad($filename);

    parent::createKeywords($json);
  }

  /**
   * Create the wordstats table from a JSON file.
   *
   * @param string $filename
   *   The JSON file that contains the texts with assigned keywords.
   */
  public function createWordstats($filename = 'keyword_texts.json') {
    $json = $this->jsonLoad($filename);

    $texts = array();
    foreach($json as $tid => $keyword_texts) {
      $texts = array_merge($texts, $keyword_texts);
    }

    return parent::createWordstats($texts);
  }

  /**
   * Create the keyword-word relations table from a JSON file.
   *
   * @param string $filename
   *   The JSON file that contains the texts with assigned keywords.
   */
  public function createWordRelations($filename = 'keyword_texts.json') {
    $json = $this->jsonLoad($filename);

    parent::createWordRelations($json);
  }


  /**
   * Load a JSON file.
   *
   * @param string $filename
   *   Name of the JSON file to be loaded.
   */
  private function jsonLoad($filename) {
    if (!is_file($filename)) {
      throw new Exception("No file named '$filename'.");
    }
    $file_contents = file_get_contents($filename);

    $json = json_decode($file_contents, TRUE);

    if ($json === NULL) {
      $err = json_errcode_to_text(json_last_error());
      throw new Exception("JSON $err.");
    }

    return $json;
  }

  /**
   * Translate JSON error code to text.
   *
   * @param string $errcode
   *   The JSON error code.
   *
   * @return string
   *   The error message text corresponding to the error code.
   */
  private function json_errcode_to_text($errcode) {
    $err = '';
    switch ($errcode) {
      case JSON_ERROR_NONE:
          $err = ' - No errors';
      break;
      case JSON_ERROR_DEPTH:
          $err = ' - Maximum stack depth exceeded';
      break;
      case JSON_ERROR_STATE_MISMATCH:
          $err = ' - Underflow or the modes mismatch';
      break;
      case JSON_ERROR_CTRL_CHAR:
          $err = ' - Unexpected control character found';
      break;
      case JSON_ERROR_SYNTAX:
          $err = ' - Syntax error, malformed JSON';
      break;
      case JSON_ERROR_UTF8:
          $err = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
      break;
      default:
          $err = ' - Unknown error';
      break;
    }

    return $err;
  }

}

