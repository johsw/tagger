<?php
/**
 * @file
 * Contains Disambiguator.
 */

/**
 * Contains disambiguation functionality.
 *
 * When the NamedEntityMatcher finds multiple matches (`Tag`s) for a word in the
 * text, it returns a Tag with multiple meanings ($tag->meanings).
 * For example "America" is both a band name, name of several cities, a
 * supercontinent, colloquial name of a country and so on.
 * The Disambiguator uses the disambiguation table in the database to find
 * related words to each of these `meanings`, and then picks the meaning with
 * most related words occurring in the text.
 *
 * This functionality is relevant because different `meanings` is really
 * different Tags with different tids and different linked data. And so to
 * return the correct linked data, disambigutation must occur.
 */
class Disambiguator {


  private $tags;

  /**
   * Constructs a Disambiguator object.
   */
  public function __construct($tags, $text) {
    $this->tags = $tags;
    $this->tag_ids = array();
    $this->text = $text;
    foreach ($this->tags as $vocabulary) {
      foreach($vocabulary as $tid => $tag) {
        $this->tag_ids[] = $tid;
      }
    }
  }

  /**
   * Disambiguation function.
   *
   * Runs through each ambiguous Tag (those that have multiple meanings),
   * finds the most probable meaning and sets the `tid` of the Tag accordingly.
   */
  public function disambiguate() {

    if (!isset($this->tags)) {
      return;
    }
    foreach ($this->tags as $vocabulary => $tids) {
      foreach($tids as $tid => $tag) {
        if ($tag->ambiguous) {
          $checked_tid = $this->checkRelatedWords($tag);
          if ($checked_tid != 0 && $checked_tid != $tid) {
            $temp = $this->tags[$vocabulary][$tid];
            $this->tags[$this->getVocabulary($checked_tid)][$checked_tid] = $temp;
            unset($this->tags[$vocabulary][$tid]);
          }
        }
      }
    }
    return $this->tags;
  }

  /**
   * Disambiguates a single Tag.
   *
   * For each meaning of the Tag it fetches the related words (via
   * getRelatedWords()) and chooses the meaning with the most related words
   * occurring in the text.
   *
   * @param Tag $tag
   *   The that should be disambiguated.
   *
   * @return int
   *   The tid of the meaning the with most occurences of related words in the
   *   text.
   */
  public function checkRelatedWords($tag) {
    $related_words = $this->getRelatedWords($tag);
    $max_matches = 0;
    $current = 0;

    foreach ($related_words as $tid => $words) {
      $subtotal = 0;
      foreach ($words as $word) {

        //Check for each related word how many times it occurs in the text
        $subtotal += substr_count($this->text, $word);
        if($subtotal > $max_matches) {
          $current = $tid;
          $max_matches = $subtotal;
        }
      }
    }
    return $current;
  }

  /**
   * Gets related words from the disambigutation table.
   *
   * Related words in the disambiguation sense are words that would likely occur
   * together with a specific `meaning` of the Tag.
   * E.g. 'band', 'music', 'album' are words that would likely occur together
   * with the band 'America' and so are related to the "band" meaning of
   * 'America'.
   *
   * @param Tag $tag
   *   The tag for which to find related words.
   *
   * @return array
   *   An associative array with tids as keys and values that are arrays of
   *   words related to each tid.
   */
  public function getRelatedWords($tag) {
    $tagger_instance = Tagger::getTagger();
    $db_conf = $tagger_instance->getConfiguration('db');
    $disambiguation_table = $db_conf['disambiguation_table'];

    $sql = sprintf("SELECT r.tid, GROUP_CONCAT(r.name) AS words FROM $disambiguation_table AS r WHERE r.tid IN (%s) GROUP BY r.tid", $tag->meanings);
    $matches = array();
    $result = TaggerQueryManager::query($sql);
    if ($result) {
      while ($row = TaggerQueryManager::fetch($result)) {
        $matches[$row['tid']] = explode(',', $row['words']);
      }
    }
    return $matches;
  }

  /**
   * Get the vocabulary for a particular tag.
   *
   * Gets the vid (vocabulary id) for specific tid (term id) in the database.
   *
   * @param int $tid
   *   The tid for which to find the vid.
   */
  public function getVocabulary($tid) {
    $db_conf = Tagger::getConfiguration('db');
    $lookup_table = $db_conf['lookup_table'];

    $sql = sprintf("SELECT c.vid FROM $lookup_table AS c WHERE c.tid = %s LIMIT 0,1", $tid);
    $result = TaggerQueryManager::query($sql);
    $row = TaggerQueryManager::fetch($result);
    $vocab_names = Tagger::getConfiguration('vocab_names');
    return $row['vid'];
  }
}

