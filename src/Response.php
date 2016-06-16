<?php

namespace Logikos\Http;

use Phalcon\Http\Response as PhResponse;
use Phalcon\Registry;

class Response extends PhResponse {
  
  protected $_responseData;
  
  /**
   * Set location header without changing http_response_code (because php changes the stupid code on its own trying to help you out)
   * @param unknown $location
   * @return \Kona\response
   */
  public function setLocation($location) {
    $c = http_response_code();
    $this->_setHeader('Location',$location);
    http_response_code($c);
    return $this;
  }

  public function setData($data=array()) {
    // array $data
    if (is_object($data) || is_array($data))
      foreach($data as $k->$v)
        $this->appendData($k,$v);
    
    else
      throw new \InvalidArgumentException();
    
    return $this;
  }
  public function appendData($name, $value) {
    $this->_data()->$name = $value;
    return $this;
  }

  public function respond($status_code=null, $status_msg=null) {
    $this->setStatusCode($status_code, $status_msg);
    $this->getDI()->get('view')->disable();
    $this->_output();
  }
  
  
  protected function canSendJson() {
    /* @var $request \Logikos\Http\Request */
    $request = $this->getDI()->get('request');
    $accept = $request->willAccept('json');
  }
  
  protected function _setHeader($key,$value=null) {
    // @todo send something to a log if headers are already sent?
    if (!headers_sent()) {
      $header = is_null($value)
        ? "{$key}"
        : "{$key}: {$value}";
      
      header($header,true);
    }
  }
  protected function _data() {
    if (is_null($this->_responseData))
      $this->_responseData = new Registry;
    
    return $this->_responseData;
  }
}