<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\Installation\{Catalog,Installer};
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Auth\Totp;
$directory=dirname(__DIR__).'/config';
if(is_file($directory.'/local.php')||getenv('DENDRILA_SITE_ID')){http_response_code(410);exit('Installation terminée.');}
App::session();
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$csrf=new SessionCsrf();$error='';$steps=['Accès','Thème','Modules','Base de données','Administrateur'];
$state=$_SESSION['install']??['step'=>0];
if(($state['authorized_until']??PHP_INT_MAX)<time())$state=['step'=>0];
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new InvalidArgumentException('Formulaire expiré.');
        if(isset($_POST['back']) && $state['step']>1 && ($state['authorized_until']??0)>=time()){$state['step']=max(1,$state['step']-1);}
        else switch($state['step']){
            case 0:
                $record=is_file($directory.'/install-token.json')?json_decode((string)file_get_contents($directory.'/install-token.json'),true):null;
                $token=is_string($_POST['token']??null)?$_POST['token']:'';
                if(!is_array($record)||($record['expires']??0)<time()||!hash_equals($record['hash'],hash('sha256',$token)))throw new InvalidArgumentException('Clé incorrecte ou expirée. Préparez l’installation depuis le serveur.');
                session_regenerate_id(true);$state=['step'=>1,'authorized_until'=>min(time()+3600,$record['expires'])];break;
            case 1:
                $theme=is_string($_POST['theme']??null)?$_POST['theme']:'';
                if(!isset(Catalog::themes()[$theme]))throw new InvalidArgumentException('Choisissez un thème.');
                $state['theme']=$theme;$state['step']=2;break;
            case 2:
                $state['addons']=Catalog::selection($_POST['addons']??[]);$state['step']=3;break;
            case 3:
                foreach(Catalog::requirements($state['addons'])['extensions'] as $ext)if(!extension_loaded($ext))throw new InvalidArgumentException('Extension PHP à activer : '.$ext);
                $db=[];foreach(['host','port','name','user','password'] as $key)$db[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
                try{Installer::connect($db);}catch(PDOException){throw new InvalidArgumentException('Connexion impossible. Vérifiez les identifiants, le serveur et les droits sur la base.');}
                $state['db']=$db;$state['secret']=$state['secret']??Totp::secret();$state['step']=4;break;
            case 4:
                $account=[];foreach(['name','email','password','code','url'] as $key)$account[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
                Installer::install($state,$account,$directory);unset($_SESSION['install']);session_regenerate_id(true);
                echo App::page('Votre site est prêt','<p>Le thème et les modules choisis sont installés. Vous pouvez maintenant modifier vos pages.</p><p><a class="button" href="/admin/">Ouvrir l’administration</a></p><p>Attendez le prochain code de votre application d’authentification pour vous connecter.</p>');exit;
        }
    }catch(InvalidArgumentException|DomainException $e){$error=$e->getMessage();http_response_code(422);}
    catch(Throwable $e){error_log('Dendrila installation: '.$e::class);$error='Installation interrompue. Consultez les journaux serveur. Si des tables ont été créées, utilisez une nouvelle base vide ; aucune donnée existante ne sera effacée.';http_response_code(500);}
}
$_SESSION['install']=$state;
$body='<ol class="setup-steps">';foreach($steps as $i=>$label)$body.='<li'.($i===$state['step']?' aria-current="step"':'').'>'.App::h($label).'</li>';$body.='</ol>';
if($error!=='')$body.='<p class="notice" role="alert">'.App::h($error).'</p>';
$body.='<form method="post"><input type="hidden" name="csrf" value="'.App::h($csrf->token()).'">';
$input=static fn(string $label,string $name,string $type='text',string $value=''):string=>'<label class="field">'.App::h($label).'<input required name="'.$name.'" type="'.$type.'" value="'.App::h($value).'"'.($type==='password'?' autocomplete="new-password"':'').'></label>';
switch($state['step']){
case 0:$body.='<p>Bienvenue dans Dendrila. La clé préparée sur le serveur réserve cette installation à son propriétaire.</p>'.$input('Clé d’installation','token','password');break;
case 1:
    $body.='<h2>Quelle ambiance pour votre site ?</h2><p>Le contenu est identique dans chaque aperçu pour comparer les présentations.</p><div class="theme-grid">';
    foreach(Catalog::themes() as $id=>$theme){$body.='<label class="theme-card"><span class="theme-preview"><iframe title="Aperçu : '.App::h($theme['label']).'" src="/theme-preview.php?theme='.rawurlencode($id).'" tabindex="-1" loading="eager" sandbox></iframe></span><span><input required type="radio" name="theme" value="'.App::h($id).'"'.(($state['theme']??'')===$id?' checked':'').'> <strong>'.App::h($theme['label']).'</strong></span><p>'.App::h($theme['description']).'</p></label>';}
    $body.='</div>';break;
case 2:
    $body.='<h2>De quoi avez-vous besoin ?</h2><p>Pages, menu et administration sont inclus. Cochez seulement les fonctions utiles.</p>';
    foreach(Catalog::addons() as $id=>$module)$body.='<label class="addon-card"><input type="checkbox" name="addons[]" value="'.$id.'"'.(in_array($id,$state['addons']??[],true)?' checked':'').'><span><strong>'.App::h($module['label']).'</strong><br>'.App::h($module['description']).'</span></label>';
    break;
case 3:
    $body.='<h2>Relier votre base de données</h2><ul>';foreach(Catalog::requirements($state['addons'])['database_reasons'] as $reason)$body.='<li>'.App::h($reason).'</li>';
    $body.='</ul><p>Créez une base MariaDB vide dans votre hébergement, puis renseignez son accès. Une seule base suffit.</p>'.$input('Serveur','host','text',$state['db']['host']??'localhost').$input('Port','port','number',$state['db']['port']??'3306').$input('Nom de la base','name','text',$state['db']['name']??'').$input('Utilisateur de la base','user','text',$state['db']['user']??'').$input('Mot de passe de la base','password','password');break;
case 4:
    $body.='<h2>Votre site et votre compte</h2>'.$input('Nom du site','name').$input('Adresse HTTPS du site','url','url').$input('Votre e-mail','email','email').$input('Votre mot de passe (12 caractères minimum)','password','password').'<h3>Protéger votre compte</h3><p>Ajoutez un compte dans votre application d’authentification : clé ci-dessous, codes à 6 chiffres, période de 30 secondes.</p><p><code class="secret">'.App::h($state['secret']).'</code></p><p>Conservez cette clé dans votre gestionnaire de mots de passe : elle permet de rétablir votre second facteur.</p>'.$input('Code actuel à 6 chiffres','code');break;
}
$body.='<div class="setup-actions">';if($state['step']>1)$body.='<button class="button secondary" name="back" value="1" formnovalidate>Retour</button>';
$body.='<button class="button">'.($state['step']===4?'Installer mon site':'Continuer').'</button></div></form>';
echo App::page('Installons votre site',$body);
