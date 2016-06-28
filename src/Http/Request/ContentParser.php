<?php
namespace Logikos\Http\Request;

use Logikos\Http\Request\ContentParser\Adapter\Formdata;

/**
 * 
 * @author tempcke
 * 
 */
class ContentParser {
  use \Logikos\UserOptionTrait;
  
  private $_defaultOptions = [
      'overwrite_post_global'  => false,
      'overwrite_files_global' => false
  ];
  
  public function __construct(array $userOptions = []) {
    $this->_setDefaultUserOptions($this->_defaultOptions);
    $this->mergeUserOptions($userOptions);
  }
  
  protected function overwriteGlobals($data) {
    if ($this->getUserOption('overwrite_post_global')) {
      if (is_array($data))
        $_POST = $data;
      elseif (!empty($data->post))
        $_POST = $data->post;
    }
    if ($this->getUserOption('overwrite_files_global')) {
      if (!empty($data->files))
        $_FILES = $data->files;
    }
  }
  
  public function parseJson($content) {
    $data = (array) $this->jsonDecode($content);
    $this->overwriteGlobals($data);
    return $data;
  }
  public function parseUrlencoded($content) {
    if ($this->shouldReturnEmpty($content)) return [];
    parse_str($content,$data);
    $this->overwriteGlobals($data);
    return $data;
  }
  
  // http://stackoverflow.com/a/18678678
  public function parseFormdata($content) {
    return $this->_parse(new Formdata, $content);
  }
  
  protected function _parse($adapter,$content) {
    $data = $adapter->parse($content);
    $this->overwriteGlobals($data);
    return $data;
  }
  
  /**
   * Important difference between this and php's json_decode
   * - stringified json must be an array or an object to be considered valid
   * - ex: '123' is considered valid json by php but not by this method
   * @param string $content
   * @throws \UnexpectedValueException
   * @return stdClass|array
   */
  public function jsonDecode($content) {
    if ($this->shouldReturnEmpty($content)) return [];
    $content = trim($content);
    if (!in_array(substr($content,0,1),['[','{'])) {
      throw new \UnexpectedValueException('Invalid Json');
    }
    $json = json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \UnexpectedValueException(json_last_error_msg());
    }
    return $json;
  }
  
  protected function shouldReturnEmpty($content) {
    if (trim($content) === '' || is_null($content))
      return true;
  }
}