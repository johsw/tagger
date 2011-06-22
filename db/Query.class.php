<?php

class TaggerQueryManager {
  protected $manager = NULL;

  private function __construct() {

  }

  public static function getQueryManager($config_type) {
    if (!isset(self::$manager)) {
      switch ($config_type) {
        case 'conf.php':
          include 'DatabaseBuddy.class.php';
          self::$manager = DatabaseBuddy::instance();
          break;
        case 'drupal':
          include 'DrupalDBBuddy.class.php';
          self::$manager = DrupalDBBuddy::instance();
          break;
        default:
          // TODO. Make some noise.
      }
    }
    return self::$manager;
  }
}