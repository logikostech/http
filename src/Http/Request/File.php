<?php

namespace Logikos\Http\Request;

use Phalcon\Http\Request\File as PhalconFile;
use Logikos\ArrayAccessInterface;
use Logikos\ArrayAccessTrait;

/**
 * The purpose of this wrapper is just to allow the object to be
 * accessed in the same way $_FILES[0] would have been for a single file
 * as some old code not made for Phalcon may try to use it the same way
 * 
 * object can be accessed as an array to get triditional $_FILES[0] keys:
 *  - $file['name']
 *  - $file['type']
 *  - $file['tmp_name']
 *  - $file['error']
 *  - $file['size']
 */
class File extends PhalconFile implements ArrayAccessInterface {
  use ArrayAccessTrait;
  
  public function __construct(array $file, $key = null) {
    parent::__construct($file,$key);
    $this->offsetImport($file);
  }
  
  public function toArray() {
    return isset($this->_arrayAccessContainer)
        ? $this->_arrayAccessContainer
        : [
            'name'     => $this->getName(),
            'type'     => $this->getType(),
            'tmp_name' => $this->getTempName(),
            'error'    => $this->getError(),
            'size'     => $this->getSize()
        ];
  }
}