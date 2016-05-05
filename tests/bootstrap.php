<?php

include __DIR__ . "/../vendor/autoload.php";

$di = new Phalcon\DI\FactoryDefault();

$di->set("request","Logikos\\Http\\Request",true);

Phalcon\DI::setDefault($di);