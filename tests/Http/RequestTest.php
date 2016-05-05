<?php

namespace Logikos\Tests\Http;

use Logikos\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase {
  protected $di;
  protected $request;
  
  public function setup() {
    $this->di = \Phalcon\Di::getDefault();
    $this->setReqMethod('GET');
    $this->request = new Request();
    $this->request->setDI($this->di);
  }
  
  protected function setReqMethod($method) {
    $_SERVER['REQUEST_METHOD'] = $method;
  }
  protected function setHackMethod($method) {
    $_POST['_method'] = $method;
  }
  public function testIsType() {
    $this->setReqMethod('POST');
    $this->assertTrue($this->request->isType('POST'));
  }
  public function testIsTypeList() {
    $this->setReqMethod('PATCH');
    $this->assertTrue($this->request->isType('PATCH,GET'));
  }
  public function testPostMethodHack() {
    $this->setReqMethod('POST');
    $this->setHackMethod('PUT');
    $this->request=new Request();
    $this->assertEquals($this->request->getMethod(), 'PUT');
    $this->assertTrue($this->request->isMethod('PUT'));
    $this->assertTrue($this->request->isType('PUT'));
  }
}