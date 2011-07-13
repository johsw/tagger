<?php

class TaggerLogHandler {
  const NONE     = 0;
  const ERROR    = 1;
  const WARNING  = 2;
  const STANDARD = 3;
  const VERBOSE  = 4;
  private static $LOG_TYPE = array('None', 'Error', 'Warning', 'Standard', 'Verbose');

  const FILE_LOG     = 3;
  const DB_LOG       = 4;
  const TERMINAL_LOG = 5;

  public static $loggingLevel;
  public static $loggingType;

  private static $instance;
  private static $tagger;

  private function __construct($logging_level = NULL, $logging_type = NULL) {

    self::$tagger = Tagger::getTagger();

    if($logging_level == NULL) {
      $conf = strtolower(self::$tagger->getConfiguration('logging_level'));
      if($conf == 'none') {
        self::$loggingLevel = self::NONE;
      }
      elseif($conf == 'error') {
        self::$loggingLevel = self::ERROR;
      }
      elseif($conf == 'warning') {
        self::$loggingLevel = self::WARNING;
      }
      elseif($conf == 'verbose') {
        self::$loggingLevel = self::VERBOSE;
      }
      else {
        self::$loggingLevel = self::STANDARD;
      }
    }
    if($logging_type == NULL) {
      $conf = strtolower(self::$tagger->getConfiguration('logging_type'));
      if($conf == 'db') {
        self::$loggingType = self::DB_LOG;
      }
      elseif($conf == 'terminal') {
        self::$loggingType = self::TERMINAL_LOG;
      }
      else {
        self::$loggingType = self::FILE_LOG;
      }
    }
  }

  public static function getLogger($configuration = array()) {
    if (!isset(self::$instance)) {
        $c = __CLASS__;
        self::$instance = new $c($configuration);
    }
    return self::$instance;
  }


  public function logMsg($msg, $level = self::STANDARD) {
    if (!isset(self::$instance)) {
      $c = __CLASS__;
      self::$instance = new $c;
    }


    if($level <= self::$loggingLevel) {
      if(self::$loggingType == self::FILE_LOG) {
        self::logFile($msg, $level);
      }
      elseif(self::$loggingType == self::DB_LOG) {
        // should be logDB() when implemented
        self::logFile($msg, $level);
      }
      elseif(self::$loggingType == self::TERMINAL_LOG) {
        self::logTerminal($msg, $level);
      }
    }
  }

  private function logFile($msg, $level = self::STANDARD) {
    date_default_timezone_set('Europe/Copenhagen');
    $filename = __ROOT__ . 'logs/' . date('Y-m-d') . '.log';
    $time = date('H:i:s');
    $log_type = self::$LOG_TYPE[$level];
    $file = fopen($filename, 'a');
    $log_msg = <<<EOH
------------------------------------ $time ------------------------------------
Log-type: $log_type
$msg
EOH;
    fwrite($file, $log_msg);
    fclose($file);
  }

  private function logTerminal($msg, $level = self::STANDARD) {
    $backtrace = debug_backtrace();
    foreach($backtrace as $entry) {
      //if ($entry['function'] == __FUNCTION__) {
      //print_r($entry);
      $log_functions = array('logVerbose', 'logStandard', 'logWarning', 'logError');
      if ($entry['class'] == 'TaggerLogManager' && in_array($entry['function'], $log_functions)) {
          $file_line = basename($entry['file']) . ', line ' . $entry['line'];
      }
    }


    $file = fopen('php://stdout', 'a');
    $log_type = self::$LOG_TYPE[$level];
    $log_msg = <<<EOH
>>> $file_line:
$msg
EOH;
    fwrite($file, $log_msg);
    fclose($file);
  }


}

