<?php

/**
 * This is a danish stemmer based on: http://drupal.org/project/danishstemmer
 * Which in turn is basod on the Snowball project:
 *    http://snowball.tartarus.org/algorithms/danish/stemmer.html
 */

class Stemmer {
  private function Stemmer() {}
  private static $endings = array('erendes', 'erende', 'hedens', 'endes', 'erede', 'erens', 'erets', 'ernes', 'ethed', 'heden', 'heder', 'ende', 'enes', 'ered', 'eren', 'erer', 'eres', 'eret', 'erne', 'heds', 'hed', 'ene', 'ens', 'ere', 'ers', 'ets', 'en', 'er', 'es', 'et', 'e');
  private static $endings_regex = '/(erendes|erende|hedens|endes|erede|erens|erets|ernes|ethed|heden|heder|ende|enes|ered|eren|erer|eres|eret|erne|heds|hed|ene|ens|ere|ers|ets|en|er|es|et|e)$/';

  // This uses the external Snowball stemmer - hence the double underscore for hiding
  public static function __stemWord($word) {
    return utf8_encode(stem_danish(utf8_decode($word)));

    // Two other methods for encoding conversion - but these are slower
    //return iconv("ISO-8859-1", "UTF-8", stem_danish(iconv("UTF-8", "ISO-8859-1//IGNORE", $word)));
    //return mb_convert_encoding(stem_danish(mb_convert_encoding($word, "ISO-8859-1", "UTF-8")), "UTF-8", "ISO-8859-1");
  }


  /* --- STEMMING ------------------------------------------------------------- */

  /**
   * Stem a Danish word.
   */
  public static function stemWord($word) {
    $word = mb_strtolower($word);

    /**
     * R1 is the region after the first non-vowel following a vowel, or is the
     * null region at the end of the word if there is no such non-vowel.
     */
    $r1 = '';
    if (preg_match('/[aeiouyæøå][^aeiouyæøå]/u', $word, $matches, PREG_OFFSET_CAPTURE)) {
      $r1 = $matches[0][1] + 2;
    }

    // steps 1-4: suffix removal
    if ($r1) {

    /**
     * Step 1: Search for the longest among the following suffixes in R1, and
     * perform the action indicated.
     *
     * (a) hed ethed ered e erede ende erende ene erne ere en heden eren er heder
     *     erer heds es endes erendes enes ernes eres ens hedens erens ers ets
     *     erets et eret
     *
     *     Action: Delete
     *
     * (b) s
     *
     *     Action: delete if preceded by a valid s-ending (of course the letter of
     *     the valid s-ending is not necessarily in R1)
     */
      //$word = preg_replace(self::$endings_regex, '', $word, 1);

      //$word = preg_replace('/([abcdfghjklmnoprtvyzå])s$/', '\\1', $word);
      if (preg_match('/([abcdfghjklmnoprtvyzå])s$/', $word)) {
        $word = substr($word, 0, -1);
      }

    /**
     * Step 2: Search for one of the following suffixes in R1, and if found delete
     * the last letter.
     *
     *    gd dt gt kt
     *
     * (For example, friskt -> frisk)
     */
      $word = preg_match('/(gd|dt|gt|kt)$/', $word) ? substr($word, 0, -1) : $word;

    /**
     * Step 3: If the word ends in igst, remove the final st.
     *
     * Search for the longest among the following suffixes in R1, and perform the
     * action indicated.
     *
     * (a) ig lig elig els
     *
     *     Action: delete, and then repeat step 2
     *
     * (b) løst
     *
     *     Action: replace with løs
     */
      $word = preg_match('/igst$/', $word) ? substr($word, 0, -2) : $word;

      $c = 0;
      $word = preg_replace('/(elig|els|lig|ig)$/', '', $word, 1, $c);
      if ($c == 1) {
        $word = preg_match('/(gd|dt|gt|kt)$/', $word) ? substr($word, 0, -1) : $word;
      }

      //if (mb_substr($word, -4) == 'løst') {
      //  $word = substr($word, 0, -1);
      //}
      $word = preg_replace('/løst$/', 'løs', $word);


    /**
     * Step 4: If the word ends with double consonant in R1, remove one of the
     * consonants.
     *
     * For example, bestemmelse -> bestemmels (step 1) -> bestemm (step 3a)
     * ->bestem in this step.
     */
      $word = preg_match('/(bb|cc|dd|ff|gg|hh|jj|kk|ll|mm|nn|pp|qq|rr|ss|tt|vv|xx|zz)$/', $word) ? substr($word, 0, -1) : $word;
    }
    return $word;
  }
}
