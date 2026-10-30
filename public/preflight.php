<?php
declare(strict_types=1);
// Deliberately independent of Composer's autoloader, including on an unsupported PHP runtime.
$base=dirname(__DIR__);
if(is_file($base.'/config/local.php')){http_response_code(410);exit('Installation déjà terminée.');}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
header('Content-Type: text/html; charset=utf-8');
$root=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));
$secure=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
$local=getenv('APP_ENV')==='local' && in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
$items=[
  'PHP 8.5 ou supérieur'=>PHP_VERSION_ID>=80500,
  'Racine du site pointant sur public/'=>$root!==false&&$root===realpath(__DIR__),
  'HTTPS valide ou test local'=>$secure||$local,
  'Autoloader Composer inclus dans le paquet'=>is_file($base.'/vendor/autoload.php'),
  'Dossier config/ privé et accessible'=>is_dir($base.'/config')&&is_writable($base.'/config')&&!is_link($base.'/config'),
  'Dossier storage/ privé et accessible'=>is_dir($base.'/storage')&&is_writable($base.'/storage')&&!is_link($base.'/storage'),
];
foreach(['json','mbstring','pdo','pdo_mysql','gd','fileinfo','sodium','ctype'] as $ext){
    $items['Extension PHP : '.$ext]=extension_loaded($ext);
}
$ok=!in_array(false,$items,true);
$esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prérequis — Dendrila CMS</title><style>body{background:#fafaf8;color:#191718;max-width:52rem;margin:3rem auto;padding:0 1rem;font:1rem/1.65 system-ui,sans-serif}h1{font-size:clamp(1.7rem,4vw,2.4rem)}li{padding:.45rem 0}strong{font-weight:750}.ok{color:#206345}.bad{color:#a12634}a{color:#6f2943}a:focus-visible{outline:3px solid #202060;outline-offset:4px}</style></head><body>';
echo '<h1>Vérification avant installation</h1><p>Ces vérifications concernent le serveur PHP. Aucune base de données n’est créée ni modifiée.</p><ul>';
foreach($items as $name=>$pass){
    echo '<li><strong class="'.($pass?'ok':'bad').'">'.($pass?'OK':'À corriger').'</strong> — '.$esc($name).'</li>';
}
echo '</ul>';
if($ok)echo '<p>Les prérequis locaux sont satisfaits. Il faudra encore disposer d’une base MariaDB vide et de ses identifiants.</p><p><a href="/install.php">Reprendre l’installation</a></p>';
else{http_response_code(503);echo '<p>Corrigez les éléments signalés, puis rechargez cette page. Vérifiez notamment la racine web dans le panneau de votre hébergeur. Ne publiez jamais config/, vendor/ ou storage/.</p>';}
echo '</body></html>';
