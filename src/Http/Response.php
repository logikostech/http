<?php

namespace Logikos\Http;

use Phalcon\Http\Response as PhResponse;
use Phalcon\Registry;

class Response extends PhResponse {
  
  protected $_responseData;
  

  public function setJsonContent($content, $jsonOptions = 0, $depth = 512) {
    parent::setJsonContent($content, $jsonOptions, $depth);
    $this->setContentType('application/json', 'UTF-8');
    $this->setHeader('E-Tag', md5($this->getContent()));
  }
    
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
  
  public function getData($key = null) {
    if (is_null($key))
      return $this->_responseData;
    
    return isset($this->_responseData[$key])
        ? $this->_responseData[$key]
        : null;
  }
  public function setData($data=array()) {
    // array $data
    if (is_object($data) || is_array($data))
      foreach($data as $k=>$v)
        $this->appendData($k,$v);
    
    else
      throw new \InvalidArgumentException();
    
    return $this;
  }
  public function appendData($name, $value) {
    $this->responseData()->$name = $value;
    return $this;
  }
  protected function responseData() {
    if (is_null($this->_responseData)) {
      $this->_responseData = new Registry;
    }
    return $this->_responseData;
  }
  

  public function respond($status_code=null, $status_msg=null) {
    $this->_setStatus($status_code, $status_msg);
    if (!is_null($this->getData())) {
      $this->sendData($this->getData());
    }
    else {
      $this->send();
    }
  }
  public function sendData($data, $status_code=null, $status_msg=null) {
    $this->_setStatus($status_code, $status_msg);
    if ($this->canSendJson()) {
      $this->sendDataAsJson($data);
    }
    else {
      $this->sendDataAsHtml($data);
    }
  }
  protected function _setStatus($code=null, $msg=null) {
    if ($code) {
      $this->setStatusCode($code, $msg);
    }
  }
  /**
   * format and send json_encode'able object or array
   * @param mixed $data
   */
  public function sendDataAsHtml($data) {
    $this->disableView();
    if (extension_loaded('xdebug')) {
      ob_start();
      var_dump($data);
      $output = ob_get_clean();
    }
    else {
      $output = sprintf(
          '<pre><code>%s</code></pre>',
          json_encode($data,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)
      );
    }
    $this->setContent($output);
    $this->send();
  }
  
  /**
   * jsonify and send data
   * @param mixed $data
   */
  public function sendDataAsJson($data) {
    $this->disableView();
    $this->setContentType('application/json', 'UTF-8');
    if (is_object($data)) {
      if (method_exists($data,'toArray'))
        $data = $data->toArray();
    }
    if (empty($data))
      $data = new \stdClass();
    
    $this->setJsonContent($data);
    $this->send();
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
  protected function disableView() {
    if ($this->getDI()->get('view')) {
      $this->getDI()->get('view')->disable();
    }
  }
}