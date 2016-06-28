<?php

namespace Logikos\Tests\Http;

use Logikos\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase {
  public static $basedir;
  protected $di;
  protected $request;
  protected $srvsetcache = [];

  public static function setUpBeforeClass() {
    static::$basedir = realpath(substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7));
    require_once static::$basedir.'/_bootstrap.php';
  }
  
  public function setup() {
    $this->_cleanupSetServerVars();
    $this->di = new \Phalcon\Di;
    $this->di->set('request','\\Logikos\\Http\\Request');
    $this->setReqMethod('GET');
    $this->request = new Request;
    $this->request->setDI($this->di);
  }
  
  public function testInstanceOf() {
    $expected = 'Logikos\Http\Request';
    $this->assertInstanceOf(
        $expected,
        $this->request,
        'The new object is not of the correct class.'
    );
  }
  public function testCanBuildCorrectMimeType() {
    $m = [
        'xml'    => 'application/xml',
        'json'   => 'application/json',
        'jpeg'   => 'image/jpeg',
        'gif'    => 'image/gif',
        'html'   => 'text/html',
        'plain'  => 'text/plain',
        'text/*' => 'text/*'
    ];
    foreach($m as $k=>$v) {
      $this->assertEquals($v, $this->request->getMime($k));
    }
  }
  
  public function testContentTypeIsAcceptable() {
    $acceptable = 'application/json,text/html,text/*;q=0.9,*/*;q=0.8';
    $this->_setServerVar('HTTP_ACCEPT', $acceptable);
    $will = [
        'html',
        'text/html',
        'json',
        'text/plain'
    ];
    $wont = [
        'gif',
        'image/jpeg'
    ];
    $this->assertWillAccept($will, 0.9);
    $this->assertWillAccept($wont, 0.8);
    $this->assertWillNotAccept($wont, 0.9);
  }
  public function assertWillAccept(array $mimes,$qualitylimit) {
    foreach ($mimes as $mime) {
      $this->assertTrue($this->request->willAccept($mime,$qualitylimit),"{$mime} SHOULD be acceptable with quality limit of {$qualitylimit}: {$_SERVER['HTTP_ACCEPT']}?");
    }
  }
  public function assertWillNotAccept(array $mimes,$qualitylimit) {
    foreach ($mimes as $mime) {
      $this->assertFalse($this->request->willAccept($mime,$qualitylimit),"{$mime} SHOULD NOT be acceptable with quality limit of {$qualitylimit}: {$_SERVER['HTTP_ACCEPT']}?");
    }
  }
  public function testCheckHttpMethodAginstCLS() {
    $this->setReqMethod('PATCH');
    $this->assertTrue($this->request->isType('GET,PATCH'));
    $this->assertFalse($this->request->isType('GET,POST'));
  }
  public function testPostMethodHack() {
    $this->setHackMethod('PUT');
    $this->request=new Request();
    $this->assertEquals($this->request->getMethod(), 'PUT');
    $this->assertTrue($this->request->isMethod('PUT'));
    $this->assertTrue($this->request->isType('PUT'));
  }
  
  public function testCanGetContentTypeMime() {
    $this->_setServerVar("CONTENT_TYPE",'application/json; charset=utf-8');
    $this->assertEquals('application/json', $this->request->getContentTypeMime());
    $this->assertTrue($this->request->isContentType('json'));
  }
  
  public function testContentTypeInArray() {
    $this->_setServerVar("CONTENT_TYPE",'application/json; charset=utf-8');
    $goodmatch = ['html','json'];
    $badmatch = ['html','xml'];
    $this->assertTrue($this->request->isContentType($goodmatch));
    $this->assertFalse($this->request->isContentType($badmatch));
    $this->assertTrue($this->request->isContentType('json'));
    $this->_unsetServerVar('CONTENT_TYPE');
    $this->assertFalse($this->request->isContentType('json'));
  }
  
  
  
  public function testCanAccessInputFilesAsArrayProperties() {
    $files = $this->getFiles();
    $this->assertInstanceOf(
        'ArrayAccess',
        $files,
        '$files should support array access of properties'
    );
    $this->assertTrue(
        isset($files['onefile']),
        '$files should support object access of properties'
    );
  }
  public function testCanAccessInputFilesAsObjectProperties() {
    $files = $this->getFiles();
    $this->assertTrue(
        isset($files->onefile),
        '$files should support object access of properties'
    );
  }
  public function testSingleFileParamsSetCorrectly() {
    $files = $this->getFiles();
    $this->assertEquals(
        '/tmp/phpqgn1Aj',
        $files->onefile['tmp_name'],
        'array access params not working'
    );
  }
  public function testSingleFileArrayMatchesOriginal() {
    $files = $this->getFiles();
    $this->assertEquals(
        $_FILES['onefile'],
        $files->onefile->toArray(),
        'single file upload does not match..'
    );
  }
  public function testMultiFileUploadPropertiesSetCorrectly() {
    $files = $this->getFiles();
    $this->assertEquals(
        '/tmp/phpZ1Vy0R',
        $files->multifile[0]['tmp_name'],
        'array access params not working'
    );
  }
  public function testMultiFilesAreOrganizedAsArrayOfSingleFiles() {
    $files = $this->getFiles();
    $this->assertEquals(
        [
          'name'      => 'b.txt',
          'type'      => 'text/plain',
          'tmp_name'  => '/tmp/phpZ1Vy0R',
          'error'     => 0,
          'size'      => 2
        ],
        $files->multifile[0]->toArray()
    );
    $this->assertEquals(
        [
          'name'      => 'c.txt',
          'type'      => 'text/plain',
          'tmp_name'  => '/tmp/phpJCU6pq',
          'error'     => 0,
          'size'      => 2
        ],
        $files->multifile[1]->toArray()
    );
  }
  
  // the tests for ContentParser cover many other situations that do not need repeted here
  // this test is really confirming that the requests class is automaticly useing contentparser
  public function testJsonParse() {
    $this->setReqMethod('PUT');
    $this->_setServerVar("CONTENT_TYPE",'application/json; charset=utf-8');
    $rawbody       = $this->getExampleJson();
    $this->request = new Request($rawbody);
    $expected      = (array) json_decode($rawbody);
    $actual        = $this->request->getPut();
    $this->assertEquals($expected, $actual);
  }
  public function testCanParseFormdata() {
    $this->setReqMethod('PUT');
    $this->_setServerVar("CONTENT_TYPE",'multipart/form-data; charset=utf-8');
    $rawbody       = $this->getExampleFormdata();
    $this->request = new Request($rawbody);
    $expected      = $this->getExampleFormdataResult()->post;
    $actual        = $this->request->getPut();
    $this->assertEquals($expected, $actual);
  }

  
  protected function setReqMethod($method) {
    $this->_setServerVar('REQUEST_METHOD',$method);
  }
  protected function setHackMethod($method) {
    $this->setReqMethod('POST');
    $_POST['_method'] = $method;
  }

  # Uploaded Files

  protected function getFiles() {
    $_FILES = [                                     
      'onefile'   => [
        'name'      => 'a.txt',
        'type'      => 'text/plain',
        'tmp_name'  => '/tmp/phpqgn1Aj',
        'error'     => 0,
        'size'      => 2,
      ],
      'multifile' => [
        'name'      => ['b.txt',          'c.txt'         ],
        'type'      => ['text/plain',     'text/plain'    ],
        'tmp_name'  => ['/tmp/phpZ1Vy0R', '/tmp/phpJCU6pq'],
        'error'     => [0,                0               ],
        'size'      => [2,                2               ]
      ],
    ];
    return $this->request->getFiles();
  }
  

  # example content
  protected function getExampleFormdata() {
    return file_get_contents(self::$basedir.'/var/content/formdata');
  }
  protected function getExampleFormdataResult() {
    return include self::$basedir.'/var/content/formdata.php';
  }
  protected function getExampleUrlencoded() {
    return http_build_query($this->getExampleArray());
  }
  protected function getExampleJson() {
    return json_encode($this->getExampleArray());
  }
  protected function getExampleArray() {
    return [
        'firstName' => 'John',
        'lastName'  => 'Smith',
        'age'       => 25,
        'street'    => '134 2nd Street',
        'city'      => 'New York',
        'state'     => 'NY',
        'zipcode'   => '10021',
        'phoneNum'  => [ 
            [
               'type' => 'work',
               'number' => '212 555-1234',
            ],
            [
               'type' => 'cell',
               'number' => '646 555-4567',
            ],
        ],
    ];
  }
  
  protected function _setServerVar($var, $value) {
    $this->srvsetcache[$var] = $var;
    $_SERVER[$var] = $value;
  }
  protected function _unsetServerVar($var) {
    unset($_SERVER[$var]);
    unset($this->srvsetcache[$var]);
  }
  protected function _cleanupSetServerVars() {
    foreach($this->srvsetcache as $var) {
      $this->_unsetServerVar($var);
    }
    $this->setReqMethod('GET'); // default method
  }
}