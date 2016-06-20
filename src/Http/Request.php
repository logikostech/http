<?php

namespace Logikos\Http;

use Phalcon\Registry;
use Phalcon\Http\Request\File;
use Phalcon\Http\Request\FileInterface;
use Logikos\Http\Request\File as LogikosFile;

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
      
    }
    
//     if ($this->inputNeedsParsed()) {
//       $this->_parseInput();
//     }
//       $this->_parsePut();      
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
  
  // some people like to do PUT and PATCH requests via POST beings html form method attribute only accepts GET|POST
  public function fakePostHack() {
    if ($this->isPost()) {
      $altmethod = $this->getPost('_method');
      if ($altmethod && $altmethod != 'POST') {
        if ($this->isValidHttpMethod($altmethod)) {
          $this->rawdata = $this->getPost();
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
  
  
  protected function _parsePut() {
    $data = array();

    // this supports PUT requests that are tunneled through POST via <input type="hidden" name="_method" value="put" /> 
    if (!empty($_POST['_method']) && $_POST['_method']=='PUT') {
      $data = $_POST;
    }

	  elseif ($this->getContentTypeMime('multipart/form-data')) {
  
      // Fetch content and determine boundary
      $boundary = substr($this->getRawBody(), 0, strpos($this->getRawBody(), "\r\n"));
  
      if(empty($boundary)){
          parse_str($this->getRawBody(),$data);
          $this->_setPutData($data);
          return;
      }
  
      // Fetch each part
      $parts = array_slice(explode($boundary, $this->getRawBody()), 1);
      $data = array();
  
      foreach ($parts as $part) {
        // If this is the last part, break
        if ($part == "--\r\n") break;
  
        // Separate content from headers
        $part = ltrim($part, "\r\n");
        list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);
  
        // Parse the headers list
        $raw_headers = explode("\r\n", $raw_headers);
        $headers = array();
        foreach ($raw_headers as $header) {
            list($name, $value) = explode(':', $header);
            $headers[strtolower($name)] = ltrim($value, ' ');
        }
  
        // Parse the Content-Disposition to get the field name, etc.
        if (isset($headers['content-disposition'])) {
          $filename = null;
          $tmp_name = null;
          preg_match(
              '/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
              $headers['content-disposition'],
              $matches
          );
          list(, $type, $name) = $matches;
  
          //Parse File
          if( isset($matches[4]) )        {
            //if labeled the same as previous, skip
            if( isset( $_FILES[ $matches[ 2 ] ] ) )          {
                continue;
            }
  
            //get filename
            $filename = $matches[4];
  
            //get tmp name
            $filename_parts = pathinfo( $filename );
            $tmp_name = tempnam( ini_get('upload_tmp_dir'), $filename_parts['filename']);
  
            //populate $_FILES with information, size may be off in multibyte situation
            $_FILES[ $matches[ 2 ] ] = array(
                'error'=>0,
                'name'=>$filename,
                'tmp_name'=>$tmp_name,
                'size'=>strlen( $body ),
                'type'=>$value
            );
  
            //place in temporary directory
            file_put_contents($tmp_name, $body);
          }
          //Parse Field
          else        {
            $data[$name] = substr($body, 0, strlen($body) - 2);
          }
        }
      }
    }
    elseif ($this->getContentTypeMime('application/x-www-form-urlencoded')) {
      parse_str($this->getRawBody(),$data);
    }
    elseif ($this->getContentTypeMime('application/json')) {
      $data = json_decode($this->getRawBody());
    }
    elseif (!$data = $this->_jsonDecode(trim($this->getRawBody()))) {
      parse_str($this->getRawBody(),$data);
    }
    $this->_setPutData($data);
  }
  protected function _setPutData($data) {
    $data = (array) $data;
    $this->_putCache = $data;
    $_REQUEST = array_merge($_REQUEST,$data);
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