<?php
namespace Logikos\Http\Request\ContentParser;

interface AdapterInterface {
  public function parse($content);
}