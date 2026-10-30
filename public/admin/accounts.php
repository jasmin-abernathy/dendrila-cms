<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Auth\Accounts;
use Dendrila\Cms\Storage\PdoCmsStore;
App::session();$pdo=App::database();$actor=App::identity($pdo)->currentActor();
if(!$actor){header('Location: /admin/login.php',true,303);exit;}
$store=new PdoCmsStore($pdo);if(!$store->actorHasPermission($actor->siteId,$actor->actorId,'users.manage')){http_response_code(403);exit('Accès interdit.');}
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$csrf=new SessionCsrf();$message='';$accounts=new Accounts($pdo,App::site(),getenv('DENDRILA_AUTH_KEY')?:'');
if($_SERVER['REQUEST_METHOD']==='POST')try{
    if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new InvalidArgumentException('Formulaire expiré.');
    if(($_POST['action']??'')==='disable'){$accounts->disable($actor,is_string($_POST['actor']??null)?$_POST['actor']:'');$message='<p role="status">Compte désactivé, sessions révoquées.</p>';}
    else{$token=$accounts->invite($actor,is_string($_POST['email']??null)?$_POST['email']:'',is_string($_POST['role']??null)?$_POST['role']:'');$link=(getenv('APP_BASE_URL')?:'').'/admin/join.php?token='.$token;$message='<p role="status">Invitation créée. Transmettez ce lien à la personne concernée (valable 48 heures, une seule utilisation).</p><p class="secret">'.App::h($link).'</p>';}
}catch(InvalidArgumentException $e){http_response_code(422);$message='<p role="alert">'.App::h($e->getMessage()).'</p>';}
$q=$pdo->prepare('SELECT a.actor_id,a.email,a.enabled,m.role_id FROM native_accounts a JOIN memberships m ON a.site_id=m.site_id AND a.actor_id=m.actor_id WHERE a.site_id=? ORDER BY a.email');$q->execute([App::site()]);
$hidden='<input type="hidden" name="csrf" value="'.App::h($csrf->token()).'">';$body='<a href="/admin/">Administration</a>'.$message.'<table class="member-list"><tr><th>Compte</th><th>Rôle</th><th>Accès</th></tr>';
foreach($q as $row){$body.='<tr><td>'.App::h($row['email']).'</td><td>'.App::h($row['role_id']).'</td><td>'.($row['enabled']?'Actif':'Désactivé');if($row['enabled']&&!in_array($row['role_id'],['owner','admin'],true)&&$row['actor_id']!==$actor->actorId)$body.='<form method="post">'.$hidden.'<input type="hidden" name="action" value="disable"><input type="hidden" name="actor" value="'.App::h($row['actor_id']).'"><button>Désactiver</button></form>';$body.='</td></tr>';}
$body.='</table><h2>Inviter une personne</h2><form method="post">'.$hidden.'<label class="field">E-mail<input type="email" required name="email"></label><label class="field">Rôle<select name="role"><option value="editor">Éditeur — pages et photos, avec validation</option><option value="contributor">Contributeur — ses propres brouillons</option><option value="viewer">Lecteur — consultation</option><option value="member">Membre — participation au forum</option></select></label><button class="button">Créer le lien d’invitation</button></form>';
echo App::page('Comptes et invitations',$body);
