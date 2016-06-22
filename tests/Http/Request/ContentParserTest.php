<?php
namespace Logikos\Tests\Request;

use Logikos\Http\Request\ContentParser;

class ContentParserTest extends \PHPUnit_Framework_TestCase {
  protected static $basedir;
  protected $di;
  
  public static function setUpBeforeClass() {
    static::$basedir = realpath(substr(__DIR__.'/',0,strrpos(__DIR__.'/','/tests/')+7));
    require_once static::$basedir.'/_bootstrap.php';
  }
  
  public function setup() {
    $this->parser = new ContentParser();
  }
  

  public function testEmptyStringResultsInEmptyArray() {
    $expected = [];
    foreach(['',null] as $content) {
      $this->assertEquals($expected,$this->parser->parseJson($content));
      $this->assertEquals($expected,$this->parser->parseUrlencoded($content));
    }
  }
  
  # JSON
  public function testCanParseJson() {
    $content = $this->getExampleJson();
    $result  = $this->parser->parseJson($content);
    $this->assertEquals((array) json_decode($content),$result);
  }
  /**
   * @dataProvider invalidJsonContent
   */
  public function testInvalidJson($content) {
    $this->setExpectedException('UnexpectedValueException');
    $this->parser->parseJson($content);
  }
  
  public function testCanParseUrlencoded() {
    $content = $this->getExampleUrlencoded();
    $result  = $this->parser->parseUrlencoded($content);
    parse_str($content,$expected); // i hate output vars...
    $this->assertEquals($expected, $result);
  }
  
  public function testCanParseFormdata() {
    $content  = $this->getExampleFormdata();
    $result   = $this->parser->parseFormdata($content);
    $expected = $this->getExampleFormdataResult();
    $tmpname  = $result->files['singlefile']['tmp_name'];
    
    // tmp_name should be prefixed with contentparse
    $this->assertEquals('contentparse', substr(basename($tmpname),0,12));
    
    // tmp_name ends with a random string so we need to ignore it
    $result->files['singlefile']['tmp_name']    = $expected->files['singlefile']['tmp_name'];
    $result->files['manyfiles']['tmp_name']     = $expected->files['manyfiles']['tmp_name'];
    $result->files['singleinmulti']['tmp_name'] = $expected->files['singleinmulti']['tmp_name'];
// print_r($expected->post);
// print_r($result->post);
// exit;
    $this->assertEquals($expected, $result);
  }
  
  # data providers
  public function invalidJsonContent() {
    return [
        ['abc'],
        [123]
    ];
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
}