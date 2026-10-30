<?php
// Local development only: php -S 127.0.0.1:8080 -t public public/router.php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file=realpath(__DIR__.$path);
if($file!==false && str_starts_with($file,__DIR__.DIRECTORY_SEPARATOR) && is_file($file))return false;
if($path==='/admin/' || $path==='/admin'){require __DIR__.'/admin/index.php';return;}
if($path==='/forum/' || $path==='/forum'){require __DIR__.'/forum/index.php';return;}
require __DIR__.'/index.php';
