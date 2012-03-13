<?php

class Timer {
  private $starttime;
  private $endtime;
  private $running;

  function start() {
    $this->starttime = microtime(true);

    $this->running = true;
  }

  function stop() {
    if ($this->running) {
      $this->endtime = microtime(true);
    }
    $this->running = false;
  }

  function secsElapsed() {
    if ($this->running) {
      return microtime(true) - $this->starttime;
    }
    else {
      return $this->endtime - $this->starttime;
    }
  }
}
