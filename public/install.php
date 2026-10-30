<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\Installation\{Catalog,Installer,WebOwnerProof};
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Auth\Totp;
$directory=dirname(__DIR__).'/config';
if(is_file($directory.'/local.php')||getenv('DENDRILA_SITE_ID')){http_response_code(410);exit('Installation terminée.');}
App::session();
if(realpath((string)($_SERVER['DOCUMENT_ROOT']??''))!==realpath(__DIR__)){
    http_response_code(503);
    exit(App::page('Racine du site incorrecte','<p>Pour protéger les données privées, le domaine doit pointer uniquement vers le sous-dossier <code>public/</code> de Dendrila. Corrigez ce réglage dans votre hébergement avant de commencer.</p>'));
}
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$csrf=new SessionCsrf();$error='';$steps=['Accès','Thème','Modules','Base de données','Administrateur'];
$state=$_SESSION['install']??['step'=>0];
if(($state['authorized_until']??PHP_INT_MAX)<time())$state=['step'=>0];
if(($state['step']??0)===0 && !isset($state['ownership_challenge'])){
    $state['ownership_challenge']=WebOwnerProof::createChallenge();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new InvalidArgumentException('Formulaire expiré.');
        if(isset($_POST['back']) && $state['step']>1 && ($state['authorized_until']??0)>=time()){$state['step']=max(1,$state['step']-1);}
        else switch($state['step']){
            case 0:
                $method=$_POST['auth_method']??'token';
                $expires=time()+3600;
                if($method==='file'){
                    $publicRoot=realpath(__DIR__);
                    $documentRoot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));
                    if($publicRoot===false || $documentRoot!==$publicRoot){
                        throw new DomainException('La racine publique doit pointer vers le dossier public/ du CMS.');
                    }
                    if(!WebOwnerProof::confirm($directory,(string)($state['ownership_challenge']??''))){
                        throw new InvalidArgumentException('Le fichier de preuve est absent ou incorrect. Créez-le dans config/, puis réessayez.');
                    }
                }elseif($method==='token'){
                    $record=is_file($directory.'/install-token.json')?json_decode((string)file_get_contents($directory.'/install-token.json'),true):null;
                    $token=is_string($_POST['token']??null)?$_POST['token']:'';
                    if(!is_array($record)||($record['expires']??0)<time()||!hash_equals((string)($record['hash']??''),hash('sha256',$token))){
                        throw new InvalidArgumentException('Clé incorrecte ou expirée. Vous pouvez aussi utiliser le fichier de preuve dans le gestionnaire de fichiers.');
                    }
                    $expires=min($expires,(int)$record['expires']);
                }else{throw new InvalidArgumentException('Méthode de vérification inconnue.');}
                session_regenerate_id(true);$state=['step'=>1,'authorized_until'=>$expires];break;
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
case 0:
    $body.='<p>Bienvenue dans Dendrila. Avant toute installation, prouvez que vous contrôlez les fichiers du site. Deux méthodes sont disponibles.</p>';
    $body.='<h2>Sans terminal : gestionnaire de fichiers</h2><p>Dans le gestionnaire de fichiers de votre hébergeur, créez ou remplacez le fichier <code>config/'.WebOwnerProof::FILENAME.'</code>, à côté du dossier <code>public/</code>, avec exactement cette ligne :</p>';
    $body.='<p><code id="ownership-challenge" class="secret">'.App::h($state['ownership_challenge']).'</code></p>';
    $body.='<p>Ce fichier doit rester hors de la racine web. Il sera supprimé après vérification. <a href="/preflight.php">Vérifier les prérequis</a>.</p>';
    $body.='<h2>Autre méthode : clé de préparation</h2><label class="field">Clé d’installation créée en ligne de commande (facultatif)<input name="token" type="password" autocomplete="off"></label>';
    break;
case 1:
    $body.='<h2>Quelle ambiance pour votre site ?</h2><p>Comparez les six thèmes avec le même contenu. La miniature représente un <strong>site vierge</strong>, sans module ni contenu supplémentaire. Pour découvrir les mises en page possibles, ouvrez aussi l’exemple complet. Aucun aperçu ne modifie votre sélection.</p><div class="theme-grid">';
    foreach(Catalog::themes() as $id=>$theme){
        $label=App::h($theme['label']);
        $preview='/theme-preview.php?theme='.rawurlencode($id);
        $body.='<article class="theme-card">'
            .'<label class="theme-option"><span class="theme-preview">'
            .'<iframe title="Site vierge : '.$label.'" src="'.$preview.'&amp;mode=empty" tabindex="-1" loading="lazy" sandbox></iframe></span>'
            .'<span class="theme-choice"><input required type="radio" name="theme" value="'.App::h($id).'"'
            .(($state['theme']??'')===$id?' checked':'').'> <strong>'.$label.'</strong></span>'
            .'<p>'.App::h($theme['description']).'</p></label>'
            .'<div class="theme-card__actions">'
            .'<a href="'.$preview.'&amp;mode=empty" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir le site vierge, thème '.$label.'">Voir le site vierge <span aria-hidden="true">↗</span></a>'
            .'<a href="'.$preview.'&amp;mode=demo" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir l’exemple complet, thème '.$label.'">Exemple complet <span aria-hidden="true">↗</span></a>'
            .'</div></article>';
    }
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
if($state['step']===0){
    $body.='<button class="button" name="auth_method" value="file">Vérifier le fichier et continuer</button>';
    $body.='<button class="button secondary" name="auth_method" value="token">Utiliser la clé</button>';
}else{
    $body.='<button class="button">'.($state['step']===4?'Installer mon site':'Continuer').'</button>';
}
$body.='</div></form>';
echo App::page('Installons votre site',$body);
