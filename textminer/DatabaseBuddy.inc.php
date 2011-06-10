<?php
/**
 * This file handles the calls to the database. To use it, make a file called
 * conf.php in the root of this install. The file needs to contain the server,
 * username and password in a format like this:
 *
 * <?php
 *  $tagger_conf['db]['name'] = 'db name';
 *  $tagger_conf['db]['server'] = 'servername';
 *  $tagger_conf['db]['username'] = 'yourusername';
 *  $tagger_conf['db]['password'] = 'yourpassword';
 * ?>
 */

class DatabaseBuddy {
  private $link = NULL;
  private function __construct() {
    $tagger_instance = Tagger::getTagger();
    $db_settings = $tagger_instance->getSetting('db');
    $this->link = mysql_connect($db_settings['server'], $db_settings['username'], $db_settings['password']);
    if (!$this->link) {
      die('Could not connect: ' . mysql_error());
    }
    mysql_set_charset('utf8', $this->link);
    // If you are on an older version of PHP and have trouble with the function
    // call above here, try this instead: mysql_query("SET NAMES 'utf8'");
    mysql_select_db($db_settings['name']);
  }

  public function __destruct() {
    $this->link = NULL;
  }

  public static function query($query) {
    static $static_link = NULL;
    if (NULL == $static_link) {
      $static_link = new DatabaseBuddy();
    }
    return mysql_query($query, $static_link->link);
  }
}
