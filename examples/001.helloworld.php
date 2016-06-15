<?php

require_once('../autoload.php');

$cache = \linkcache\Cache::getInstance();

$cache->set('sayhi','Hello world!');

echo $cache->get('sayhi');
