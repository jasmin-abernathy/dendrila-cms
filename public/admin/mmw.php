<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Api\NativeMmwPairing;

App::session();
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$pdo=App::database();
$actor=App::identity($pdo)->currentActor();
if($actor===null){header('Location: /admin/login.php',true,303);exit;}
if(getenv('DENDRILA_IDENTITY_PROVIDER')!=='native'){
    http_response_code(404);exit('L’association native n’est pas activée sur ce site.');
}
$manager=new NativeMmwPairing($pdo,App::site(),getenv('DENDRILA_AUTH_KEY')?:'');
$csrf=new SessionCsrf();
$notice='';
$code='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:'')){
        http_response_code(403);
        $notice='<p role="alert">Formulaire expiré. Rechargez la page.</p>';
    }else try {
        if(($_POST['action']??null)==='code'){
            $code=$manager->issueCode($actor);
        }elseif(($_POST['action']??null)==='revoke'){
            $manager->revoke($actor,is_string($_POST['device_id']??null)?$_POST['device_id']:'');
            header('Location: /admin/mmw.php?revoked=1',true,303);
            exit;
        }else throw new InvalidArgumentException('Action inconnue.');
    }catch(\DomainException|\InvalidArgumentException|\Dendrila\Cms\Authorization\AccessDenied $e){
        http_response_code(422);
        $notice='<p role="alert">'.App::h($e->getMessage()).'</p>';
    }
}
try{$devices=$manager->devices($actor);}
catch(\Dendrila\Cms\Authorization\AccessDenied $e){http_response_code(403);exit('Accès interdit.');}
$hidden='<input type="hidden" name="csrf" value="'.App::h($csrf->token()).'">';
$body='<p><a href="/admin/?section=appli">← Administration · Appli</a></p>'
    .'<h2>Associer Mon Manager Web à ce site</h2>'
    .'<p>Installez Mon Manager Web sur Android, renseignez l’adresse HTTPS du site et créez un code ci-dessous. '
    .'Le code est valable dix minutes, à usage unique. Ne le partagez pas.</p>'
    .$notice;
if(isset($_GET['revoked']))$body.='<p role="status">Accès de cet appareil révoqué.</p>';
if($code!==''){
    $body.='<p role="status">Votre code à saisir immédiatement dans l’application :</p>'
        .'<p class="secret"><strong>'.App::h($code).'</strong></p>'
        .'<p>Il ne sera plus affiché après avoir quitté cette page.</p>';
}
$body.='<form method="post">'.$hidden.'<input type="hidden" name="action" value="code">'
    .'<button class="button">Créer un code d’association</button></form>';
$body.='<h2>Appareils associés</h2>';
if($devices===[])$body.='<p>Aucun appareil associé à ce compte.</p>';
else{
    $body.='<table class="member-list"><thead><tr><th>Appareil</th><th>Association</th><th>Dernière activité</th><th>Action</th></tr></thead><tbody>';
    foreach($devices as $device){
        $body.='<tr><td>'.App::h((string)$device['device_name']).'</td>'
            .'<td>'.App::h((string)$device['created_at']).'</td>'
            .'<td>'.App::h((string)($device['last_seen_at']??'Jamais')).'</td>'
            .'<td><form method="post">'.$hidden
            .'<input type="hidden" name="action" value="revoke">'
            .'<input type="hidden" name="device_id" value="'.App::h((string)$device['device_id']).'">'
            .'<button class="button">Révoquer</button></form></td></tr>';
    }
    $body.='</tbody></table>';
}
$body.='<p>La connexion mobile utilise les droits de votre compte Dendrila. '
    .'Désactiver ce compte ou révoquer l’appareil coupe son accès à l’API.</p>'
    .'<p><a href="https://github.com/jasmin-abernathy/lepotager-site-manager/releases" rel="noopener noreferrer">Télécharger une version Android de Mon Manager Web</a></p>';
echo App::page('Associer Mon Manager Web',$body);
