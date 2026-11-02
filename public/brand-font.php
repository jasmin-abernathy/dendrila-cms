<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use Dendrila\Cms\Runtime\{Application,BrandFontStore};
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
    http_response_code(405);header('Allow: GET, HEAD');exit;
}
$ref=$_GET['font']??null;
if(!is_string($ref)||!preg_match('/^(woff2|woff|truetype|opentype):[a-f0-9]{48}$/D',$ref)){
    http_response_code(404);exit;
}
try{
    $file=BrandFontStore::file(Application::site(),$ref);
    if($file===null){http_response_code(404);exit;}
    $stream=@fopen($file['path'],'rb');
    if($stream===false){http_response_code(404);exit;}
    header('Content-Type: '.$file['mime']);header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Referrer-Policy: no-referrer');header('Access-Control-Allow-Origin: same-origin');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: '.filesize($file['path']));
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')fpassthru($stream);
    fclose($stream);
}catch(\Throwable $e){
    error_log('Brand font delivery: '.$e::class);
    http_response_code(404);
}
