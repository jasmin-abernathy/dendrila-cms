<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$dir=dirname(__DIR__).'/config';
if(is_file($dir.'/local.php')){fwrite(STDERR,"Site déjà installé.\n");exit(1);}
if(!is_dir($dir))mkdir($dir,0700,true);
$token=bin2hex(random_bytes(24));$old=umask(0077);
file_put_contents($dir.'/install-token.json',json_encode(['hash'=>hash('sha256',$token),'expires'=>time()+3600],JSON_THROW_ON_ERROR),LOCK_EX);umask($old);
fwrite(STDOUT,"Ouvrez /install.php en HTTPS et saisissez cette clé à usage unique (valable 1 heure) :\n".$token."\n");
