<?php

class TaggerLogManager {

  const NONE     = 0;
  const ERROR    = 1;
  const WARNING  = 2;
  const STANDARD = 3;
  const VERBOSE  = 4;
  const DEBUG    = 5;
  private $LOG_TYPE = array('Error', 'Warning', 'Standard', 'Verbose', 'Debug');

  const FILE_LOG = 3;
  const DB_LOG   = 4;
  const TERMINAL_LOG = 5;


  private static $instance;
  private static $tagger;

  private function __construct() {
    self::$tagger = Tagger::getTagger();
  }

  public static function logMsg($msg, $level) {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }

    $log_handler = self::$tagger->getConfiguration('log_handler');
    if (!isset($log_handler) || (isset($log_handler) && $log_handler == 'Default')) {
      include_once 'TaggerLogHandler.class.php';
      return TaggerLogHandler::logMsg($msg, $level);
    } else {
      if (class_exists($log_handler . 'LogHandler')) {
        return call_user_func($log_handler.'LogHandler::logMsg', $msg, $level);
      }
    }
  }

  // convenience functions
  public function logError($msg) {
    self::logMsg($msg, self::ERROR);
  }

  public function logWarning($msg) {
    self::logMsg($msg, self::WARNING);
  }

  public function logStandard($msg) {
    self::logMsg($msg, self::STANDARD);
  }

  public function logVerbose($msg) {
    self::logMsg($msg, self::VERBOSE);
  }

  public function logDebug($msg) {
    self::logMsg($msg, self::DEBUG);
  }

}
