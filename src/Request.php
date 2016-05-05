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
  public function __construct($puthack=true) {
    if ($this->puthack = $puthack)
      $this->_puthack();
    
    if ($this->getMethod() == 'PUT')
      $this->_parsePut();      
  }
  
  public function isType($match) {
    return $this->isMethod(explode(',',$match));
  }
  public function contentType($checktype=null) {
    static $type;
    
    if (!$type)
      $type = $this->getContentType()
          ? explode(';',strtolower($this->getContentType()))[0]
          : null;
    
    return $checktype
      ? strtolower(trim($checktype)) === $type
      : $type;
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
      $cache = array();
      foreach($_FILES as $inputName => $file) {
        if (!is_array($file['name'])) {
          $cache[$inputName] = $this->_getFileObject($file);
          continue;
        }
        $f = array();
        foreach ($file as $attr=>$list)
          foreach($list as $k=>$v)
            $f[$k][$attr] = $v;

        foreach ($f as $v)
          $cache[$inputName][] = $this->_getFileObject($v);
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
  
  protected function _puthack() {
    $_SERVER['REQUEST_METHOD'] = $this->_reqMethod();
  }
  
  protected function _parsePut() {
    $data = array();

    // this supports PUT requests that are tunneled through POST via <input type="hidden" name="_method" value="put" /> 
    if (!empty($_POST['_method']) && $_POST['_method']=='PUT') {
      $data = $_POST;
    }

	  elseif ($this->contentType('multipart/form-data')) {
  
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
    elseif ($this->contentType('application/x-www-form-urlencoded')) {
      parse_str($this->getRawBody(),$data);
    }
    elseif ($this->contentType('application/json')) {
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