<?php

namespace Logikos\Http;

use Phalcon\Registry;
use Phalcon\Http\Request\File;
use Phalcon\Http\Request\FileInterface;
use Logikos\Http\Request\File as LogikosFile;
use Logikos\Http\Request\ContentParser;

/**
 * The main purpose of this class is for better handeling of PUT requests
 * but it also helps organize $_FILES the way it should have been...
 * @author tempcke
 */
class Request extends \Phalcon\Http\Request {
  
  protected $puthack;
  protected $rawdata;
  
  /**
   * Unless you set puthack to false $_POST['_method']=='PUT' will result in:
   *  - $_SERVER['REQUEST_METHOD']='PUT';
   *  - $this->_putCache = $_POST
   * @param string $puthack
   */
  public function __construct($rawBody=null) {
    // this is useful for tests...
    if (!is_null($rawBody)) {
      $this->_rawBody = $rawBody;
    }
    
    if ($this->inputNeedsParsed()) {
      $this->parseContent();
    }    
  }
  
  public function inputNeedsParsed() {
    // check for $_POST['_method'] to hack request method
    $not_fake_post   = !$this->fakePostHack();
    
    // limit to only valid methods
    $valid_method    =  $this->isValidHttpMethod($this->getMethod());
    
    // limit to requests with a defined content type
    $content_type    =  $this->getContentTypeMime();
    
    // ignore GET requests as body should be ignored: http://stackoverflow.com/a/983458 | https://groups.yahoo.com/neo/groups/rest-discuss/conversations/messages/9962
    $not_get_request = !$this->isGet();
    
    // php already handles 'normal' post requests just fine...
    $not_normal_post = !$this->isPost() || !$this->isContentType(['form-data','x-www-form-urlencoded']);
    
    return $not_fake_post
        && $valid_method
        && $content_type
        && $not_get_request
        && $not_normal_post;
  }
  
  public function parseContent($content=null, $type=null) {
    if (is_null($content)) {
      $content = $this->getRawBody();
    }
    
    $type = is_null($type)
      ? $this->getContentTypeMime()
      : $this->getMime($type);

    $contentParser = new ContentParser();
    switch ($type) {
      case 'application/json' :
        $data = $contentParser->parseJson($content);
        break;
      case 'application/x-www-form-urlencoded' :
        $data = $contentParser->parseUrlencoded($content);
        break;
      case 'multipart/form-data' :
        $result = $contentParser->parseFormdata($content);
        $data   = $result->post;
        $_FILES = $result->files;
        break;
      default :
        $data = [];
    }
    $this->setRawData($data);
  }
  
  public function getPatch($name=null, $filters=null, $defaultValue=null, $notAllowEmpty=false, $noRecursive=false) {
    if (!is_null($this->_patchCache)) {
      return $this->getHelper(
          $this->_patchCache,
          $name,
          $filters,
          $defaultValue,
          $notAllowEmpty,
          $noRecursive
      );
    }
  }
  
  // some people like to do PUT and PATCH requests via POST beings html form method attribute only accepts GET|POST
  public function fakePostHack() {
    if ($this->isPost()) {
      $altmethod = $this->getPost('_method');
      if ($altmethod && $altmethod != 'POST') {
        if ($this->isValidHttpMethod($altmethod)) {
          $this->setRawData($this->getPost());
          $_SERVER['REQUEST_METHOD'] = $altmethod;
          return true;
        }
      }
    }
  }
  
  public function getMime($match) {
    $mime = $match;
    if (!strstr($match,'/')) {
      switch (strtolower($match)) {
        case 'x-www-form-urlencoded' :
        case 'xhtml+xml' :
        case 'xml'       :
        case 'json'      :
          $mime = 'application/'.$match; break;
        
        case 'jpeg'      :
        case 'gif'       :
          $mime = 'image/'.$match; break;
        
        case 'form-data' :
          $mime = 'multipart/form-data'; break;
        
        default          :
          $mime = 'text/'.$match;
      }
    }
    return $mime;
  }
  public function willAccept($match) {
    $mime = explode('/',$this->getMime($match));
    foreach($this->getAcceptableContent() as $v) {
      $accept = explode('/',$v['accept']);
      $prefixmatch = $accept[0] == '*' || $accept[0] == $mime[0];
      $typematch   = $accept[1] == '*' || $accept[1] == $mime[1];
      if ($prefixmatch && $typematch)
        return true;
    }
    return false;
  }
  
  /**
   * This is an alias to isMethod but supports a comma seperated list in addition to an array
   * ex: $request->isType('POST,PUT') 
   * @param unknown $match
   * @return boolean
   */
  public function isType($match) {
    $match = is_array($match) ? $match : explode(',',$match);
    return $this->isMethod($match);
  }
  
  /**
   * Similar to Phalcon\Http\Request::getContentType() except it removes the charactor encodeing
   * ex: 'application/x-www-form-urlencoded; charset=UTF-8' results in 'application/x-www-form-urlencoded'
   * @return String|Null
   */
  public function getContentTypeMime() {
    return $this->getContentType()
        ? explode(';',strtolower($this->getContentType()))[0]
        : null;
  }
  /**
   * Checks if the content type matches $mime
   * @param string $mime examples: 'html', 'text/html', 'json' etc. 
   * @return boolean
   */
  public function isContentType($mime) {
    foreach ((array) $mime as $m) {
      $m = $this->getMime($m);
      if (strtolower($m) === $this->getContentTypeMime()) {
        return true;
      }
    }
    return false;
  }

  /**
   * getFiles([name]) - converts $_FILES to a more usable format
   *   $_FILES       = array (                                     
   *     'onefile'    => array (
   *       'name'      => 'a.txt',
   *       'type'      => 'text/plain',
   *       'tmp_name'  => '/tmp/phpqgn1Aj',
   *       'error'     => 0,
   *       'size'      => 2,
   *     ),
   *     'multifile'  => array(
   *       'name'      => array('b.txt',          'c.txt'          ),
   *       'type'      => array('text/plain',     'text/plain'     ),
   *       'tmp_name'  => array('/tmp/phpZ1Vy0R', '/tmp/phpJCU6pq' ),
   *       'error'     => array(0,                0                ),
   *       'size'      => array(2,                2                )
   *     ),
   *   )
   *   
   *   input->file() = array (
   *     'onefile'    => array(
   *       array('name'=>'a.txt', 'type'=>'text/plain', 'tmp_name'=>'/tmp/phpqgn1Aj', 'error'=>0, 'size'=>2)
   *     ),
   *     'multifile'  => array(
   *       array('name'=>'b.txt', 'type'=>'text/plain', 'tmp_name'=>'/tmp/phpZ1Vy0R', 'error'=>0, 'size'=>2),
   *       array('name'=>'c.txt', 'type'=>'text/plain', 'tmp_name'=>'/tmp/phpJCU6pq', 'error'=>0, 'size'=>2)
   *     ),
   *   )
   *   
   * @param string $name - optional, if given it returns the array of files for that name, else returns all files
   * @return array
   */
  public function getFiles($name=null) {
    static $cache;
    if (!$cache) {
      $cache = new \Phalcon\Config;
      foreach($_FILES as $inputName => $file) {
        if (!isset($cache->$inputName)) {
          $cache->$inputName = [];
        }
          
        if (!is_array($file['name'])) {
          $cache->$inputName = $this->_getFileObject($file);
          continue;
        }
        
        $f = array();
        foreach ($file as $attr=>$list) {
          foreach($list as $k=>$v) {
            $f[$k][$attr] = $v;
          }
        }
        foreach ($f as $v) {
          array_push($cache->$inputName,$this->_getFileObject($v));
        }
      }
    }
    if (is_null($name))
      return $cache;
    
    return isset($cache[$name])
      ? $cache[$name]
      : false;
  }
  
  protected function _getFileObject($file) {
    return new LogikosFile($file);
  }
  protected function setRawData($data) {
    $this->rawdata = (array) $data;
    $method = strtolower($this->getMethod());
    $var = "_{$method}Cache";
    $this->$var = $this->rawdata;
    $_REQUEST = array_merge($_REQUEST,$this->rawdata);
  }


  protected function _jsonDecode($string) {
    $json = json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE) ? $json : false;
  }

  protected function _reqMethod() {
    return strtoupper(empty($_POST['_method'])
        ? $_SERVER['REQUEST_METHOD']
        : $_POST['_method']
    );
  }
  

  protected static function _csl2array($list) {
    if (is_string($list))
      $list = array_map('trim',explode(',',$list));
    
    if (!is_array($list))
      throw new InvalidArgumentException('list must be a comma seperated list string or an array',err::EMAIL);
    
    return $list;
  }
  
}