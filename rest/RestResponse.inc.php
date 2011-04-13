<?php

class RestResponse {
  private $request_vars;
  private $data;
  private $http_accept;
  private $method;

  public function __construct() {
    $this->request_vars = array();
    $this->data = '';
    if (isset($_SERVER['HTTP_ACCEPT'])) {
      $this->http_accept = (strpos($_SERVER['HTTP_ACCEPT'], 'json')) ? 'json' : 'xml';
    }
    else {
      $this->http_accept = 'json';
    }
    $this->method = 'get';
  }

  public function setData($data) {
    $this->data = $data;
  }

  public function setMethod($method) {
    $this->method = $method;
  }

  public function setRequestVars($request_vars) {
    $this->request_vars = $request_vars;
  }

  public function getData() {
    return $this->data;
  }

  public function getMethod() {
    return $this->method;
  }

  public function getHttpAccept() {
    return $this->http_accept;
  }

  public function getRequestVars($index = NULL) {
    if (NULL != $index) {
      if (isset($this->request_vars[$index])) {
        return $this->request_vars[$index];
      }
      return FALSE;
    }
    return $this->request_vars;
  }
}

?>