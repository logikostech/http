<?php

namespace Logikos\Tests\Http;

use Logikos\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase {
  public static $basedir;
  protected $di;
  protected $request;

  public static function setUpBeforeClass() {
    static::$basedir = realpath(substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7));
    require_once static::$basedir.'/_bootstrap.php';
  }
  
  public function setup() {
    $this->di = new \Phalcon\Di;
    $this->di->set('request','\\Logikos\\Http\\Request');
    $this->setReqMethod('GET');
    $this->request = new Request;
    $this->request->setDI($this->di);
  }
  
  protected function setReqMethod($method) {
    $_SERVER['REQUEST_METHOD'] = $method;
  }
  protected function setHackMethod($method) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_method'] = $method;
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
    $acceptable = 'application/json,text/html;q=0.9,text/*;q=0.8';
    $_SERVER['HTTP_ACCEPT'] = $acceptable;
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
    foreach ($will as $mime) {
      $this->assertTrue($this->request->willAccept($mime),"{$mime} should be acceptable: {$acceptable}?");
    }
    foreach($wont as $mime) {
      $this->assertFalse($this->request->willAccept($mime),"{$mime} should not be acceptable: {$acceptable}?");
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
    $_SERVER["CONTENT_TYPE"] = 'application/json; charset=utf-8';
    $this->assertEquals('application/json', $this->request->getContentTypeMime());
    $this->assertTrue($this->request->isContentType('json'));
  }
  
  public function testContentTypeInArray() {
    $_SERVER["CONTENT_TYPE"] = 'application/json; charset=utf-8';
    $goodmatch = ['html','json'];
    $badmatch = ['html','xml'];
    $this->assertTrue($this->request->isContentType($goodmatch));
    $this->assertFalse($this->request->isContentType($badmatch));
    $this->assertTrue($this->request->isContentType('json'));
    unset($_SERVER['CONTENT_TYPE']);
    $this->assertFalse($this->request->isContentType('json'));
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
  public function testJsonParse() {
    $this->setReqMethod('PUT');
    $a = [
        'foo' => 'bar'
    ];
  }
}