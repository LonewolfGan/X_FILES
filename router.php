<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return;
}

if (is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    return;
}

require __DIR__ . '/index.php';
