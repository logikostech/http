<?php
namespace Logikos\Http\Request;

use Logikos\Http\Request\ContentParser\Adapter\Formdata;

/**
 * 
 * @author tempcke
 * 
 */
class ContentParser {
  public function parseJson($content) {
    return (array) $this->jsonDecode($content);
  }
  public function parseUrlencoded($content) {
    if ($this->shouldReturnEmpty($content)) return [];
    parse_str($content,$result);
    return $result;
  }
  
  // http://stackoverflow.com/a/18678678
  public function parseFormdata($content) {
    return $this->_parse(new Formdata, $content);
  }
  
  protected function _parse($adapter,$content) {
    return $adapter->parse($content);
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