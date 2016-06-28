<?php

$basedir  = realpath(__DIR__.'/..');
$composer = $basedir . "/vendor/autoload.php";
if (file_exists($composer))
  include_once $composer;

$loader = new \Phalcon\Loader;
$loader
  ->registerNamespaces([
    'Logikos' => $basedir.'/src'
  ])
  ->register();