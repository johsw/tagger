<?php
require_once 'textminer/EntityPreprocessor.class.php';
require_once 'textminer/Unmatched.class.php';
require_once 'textminer/Disambiguator.class.php';
require_once 'textminer/DatabaseBuddy.inc.php';


class TagController {

  private $response;
  private $ner_vocabs;
  private $text;
  private $tag_array;
  private $markedup_text;
  private $use_markup = FALSE;
  private $nl2br = FALSE;
  private $return_uris = FALSE;
  private $return_unmatched = FALSE;
  private $disambiguate = FALSE;

  public function __construct(RestResponse $response) {
    global $conf;
    if (!isset($conf['vocab_names']) || empty($conf['vocab_names'])) {
      throw new ErrorException('Missing vocab definition in configuration.');
    }
    $this->response = $response;
    $this->text = $response->getRequestVars('text');
    if (empty($this->text)) {
      $url = $response->getRequestVars('url');
      if (!empty($url)) {
        // Suppress errors.
        $this->text = @file_get_contents($response->getRequestVars('url'));
      }
    }
    if (empty($this->text)) {
      throw new InvalidArgumentException('No text to find tags in has been supplied.');
    }
    if (mb_detect_encoding($this->text) != 'UTF-8') {
      $this->text = utf8_encode($this->text);
    }
    $ner = $response->getRequestVars('ner');
    if (!empty($ner) && preg_match_all('/(' . implode('|', $conf['vocab_names']) . ')+[\ ]?/', $ner, $matches)) {
      $this->ner_vocabs = array_intersect_key(array_flip($conf['vocab_names']), array_flip($matches[1]));
    }
    else {
      $this->ner_vocabs = array_flip($conf['vocab_names']);
    }
    if ($response->getRequestVars('disambiguate')) {
      $this->disambiguate = $response->getRequestVars('disambiguate');
    }
    if ($response->getRequestVars('uris')) {
      $this->return_uris = $response->getRequestVars('uris');
    }
    if ($response->getRequestVars('unmatched')) {
      $this->return_unmatched = $response->getRequestVars('unmatched');
    }
    $this->use_markup = $response->getRequestVars('markup');
    $this->nl2br = $response->getRequestVars('nl2br');
  }

  public function process() {
    $entityPreprocessor = new EntityPreprocessor(strip_tags($this->text), $this->ner_vocabs, array());
    $this->tag_array = $entityPreprocessor->get_named_entity_matched_tags();
    $unmatched_words = $entityPreprocessor->get_nonmatches();
    $unmatched = new Unmatched($unmatched_words);
    $unmatched->logUnmatched();
    if ($this->return_unmatched) {
      //TODO - Process and return unmatched entities
    }
    if ($this->disambiguate) {
      $disambiguator = new Disambiguator($this->tag_array);
      $this->tag_array = $disambiguator->disambiguate();
    }
    if ($this->return_uris) {
      $this->buildUriData();
    }

    $this->markupText();
  }

  public function getProcessedResponse() {
    $return_arr =  array();
    if ($this->use_markup) {
      $return_arr['markup'] = $this->markedup_text;
    }

    $return_arr['tags'] = $this->tag_array;
    return $return_arr;
  }

  private function markupText() {
    if (!$this->use_markup) {
      return;
    }
    $this->markedup_text = $this->text;
    foreach ($this->tag_array as $terms) {
      foreach ($terms as $tid => $term) {
        $this->markedup_text = str_replace($term['navn'], '<span class="tagr-item" id="tid-' . $tid . '" property="dc:subject">' . $term['navn'] . '</span> ', $this->markedup_text);
      }
    }
    if ($this->nl2br) {
      $this->markedup_text = nl2br($this->markedup_text);
    }
  }
  private function buildUriData() {
    foreach($this->tag_array as $cat => $tags) {
      foreach($tags as $tid => $tag) {
        $uris = $this->fetchUris($tid);
        $this->tag_array[$cat][$tid]['uris'] = $uris;
      }
    }
  }
  private function fetchUris($tid) {
    global $conf;
    $sql = sprintf("SELECT dstid, uri FROM linked_data_sources WHERE tid = %s ORDER BY dstid ASC", $tid);
    $result = DatabaseBuddy::query($sql);
    $uris = array();
    while ($row = mysql_fetch_assoc($result)) {
      $uris[$conf['lod_sources'][$row['dstid']]] = $row['uri'];
    }
    return $uris;
  }
}
?>