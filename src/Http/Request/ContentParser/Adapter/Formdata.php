<?php
namespace Logikos\Http\Request\ContentParser\Adapter;

use Logikos\Http\Request\ContentParser\AdapterInterface;

/**
 * refactored from http://stackoverflow.com/a/18678678
 * @author tempcke
 */
class Formdata implements AdapterInterface {
  public $fd;
  
  /**
   * Parse multipart/form-data
   * @todo need to add support for <input type='file' name='pics[]' multiple />
   * @todo add option to override $_POST and $_FILES
   * @return stdClass {post:[], files:[]}
   */
  public function parse($content) {
    $this->resetFd();
    foreach ($this->formdataParts($content) as $part) {
      if (!$this->isLastPart($part)) {
        $this->processFormdataPart($part);
      }
    }
    $this->compileData();
    return $this->fd;
  }

  protected function resetFd() {
    $this->fd = (object) [
        'post'  => [],
        'files' => []
    ];
  }
  protected function processFOrmdataPart($part) {
    $sect = $this->formdataSections($part);
    
    if ($this->hasDisposition($sect)) {
      // Parse File
      if(isset($sect->disposition->filename))
        $this->appendFile($sect);

      // Parse Field
      else
        $this->formdataSetPostValue($sect);
    }
  }
  protected function formdataSetPostValue($sect) {
    $key   = $sect->disposition->name;
    $value = substr($sect->body, 0, strlen($sect->body) - 2);
    
    // we do it this way to support name=foo[bar][] etc...
    $this->postquerystr[] = "{$key}={$value}"; 
  }
  protected function formdataParts($content) {
    $boundary = substr($content, 0, strpos($content, "\r\n"));
    if(empty($boundary)) {
      throw new \UnexpectedValueException('Invalid form-data, no boundry found');
    }
    return array_slice(explode($boundary, $content), 1);
  }
  protected function isLastPart($part) {
    return $part == "--\r\n";
  }
  protected function formdataSections($part) {
    list($head, $body) = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
    $headers = $this->formdataHeaders($head);
    return (object) [
        'head' => $headers,
        'disposition' => $this->disposition($headers),
        'type' => isset($headers['content-type']) ? $headers['content-type'] : null,
        'body' => $body
    ];
  }
  protected function hasDisposition($sect) {
    return !empty($sect->disposition);
  }
  protected function formdataHeaders($raw_headers) {
    $raw_headers = explode("\r\n", $raw_headers);
    $headers = array();
    foreach ($raw_headers as $header) {
      $toks  = explode(':', $header);
      $label = strtolower($toks[0]);
      $value = trim($toks[1]);
      $headers[$label] = $value;
    }
    return $headers;
  }
  protected function disposition($headers) {
    if (isset($headers['content-disposition'])) {
      preg_match(
          '/^(.+); *name="(?P<name>[^"]+)"(?:; *filename="(?P<filename>[^"]+)")?/',
          $headers['content-disposition'],
          $disposition
      );
      return (object) $disposition;
    }
  }
  
  // builds structure the same way php builds $_FILES
  // has support for multiple files where name ends in [] such as name=foo[]
  // known issue: fails on deeper arrays such as if name=foo[bar][]
  protected function appendFile($sect) {
    $data = $this->filedata($sect);
    
    $multi = substr($sect->disposition->name,-2)=='[]';
    if ($multi) {
      $key = substr($sect->disposition->name,0,-2);
      foreach($data as $k=>$v) {
        $value = isset($this->fd->files[$key][$k])
          ? (array) $this->fd->files[$key][$k]
          : [];
        array_push($value,$v);
        $this->fd->files[$key][$k] = $value;
      }
    }
    else {
      $key = $sect->disposition->name;
      $this->fd->files[$key] = $data;
    }
  }
  protected function filedata($sect) {
    return array(
        'error'    => 0,
        'name'     => $sect->disposition->filename,
        'tmp_name' => $this->makeTempFile($sect->body),
        'size'     => strlen(trim($sect->body)),
        'type'     => trim($sect->type)
    );
  }
  protected function makeTempFile($content,$prefix=null) {
    if (is_null($prefix))
      $prefix='contentparse';

    $tmp_name = tempnam(ini_get('upload_tmp_dir'), $prefix);
    file_put_contents($tmp_name, $content);
    return $tmp_name;
  }
  protected function compileData() {
    parse_str(implode('&',$this->postquerystr),$this->fd->post);
  }
} 