<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\Installation\{Catalog,Installer,WebOwnerProof,HostingGuide};
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Auth\Totp;
$directory=dirname(__DIR__).'/config';
if(is_file($directory.'/local.php')||getenv('DENDRILA_SITE_ID')){http_response_code(410);exit('Installation terminée.');}
App::session();
if(realpath((string)($_SERVER['DOCUMENT_ROOT']??''))!==realpath(__DIR__)){
    http_response_code(503);
    exit(App::page('Racine du site incorrecte','<p>Pour protéger les données privées, le domaine doit pointer uniquement vers le sous-dossier <code>public/</code> de Dendrila. Corrigez ce réglage dans votre hébergement avant de commencer.</p>'));
}
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; frame-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$csrf=new SessionCsrf();$error='';$steps=['Accès','Thème','Modules','Base de données','Administrateur'];
$state=$_SESSION['install']??['step'=>0];
if(($state['authorized_until']??PHP_INT_MAX)<time())$state=['step'=>0];
if(($state['step']??0)===0 && !isset($state['ownership_challenge'])){
    $state['ownership_challenge']=WebOwnerProof::createChallenge();
}
// Older sessions can still be on the now-obsolete MariaDB connection step.
// Preserve the selected theme and modules, but continue with private SQLite.
if (($state['step']??0)===3 && ($state['authorized_until']??0)>=time()) {
    $state['addons']=Catalog::selection($state['addons']??[]);
    $state['db']=['driver'=>'sqlite'];
    $state['secret']=$state['secret']??Totp::secret();
    $state['step']=4;
}
// Downloading the text proof is optional but avoids hand-copying a random code.
// It is never written to public/ and only a private config/ proof is accepted.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
if (isset($_GET['download_proof'])) {
    if (($_SERVER['REQUEST_METHOD']??'GET') !== 'GET'
        || ($state['step']??0)!==0
        || !is_string($state['ownership_challenge']??null)) {
        http_response_code(404);
        exit('Fichier indisponible.');
    }
    $_SESSION['install']=$state;
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.WebOwnerProof::FILENAME.'"');
    header('X-Content-Type-Options: nosniff');
    echo $state['ownership_challenge']."\n";
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new InvalidArgumentException('Formulaire expiré.');
        if (($state['step']??0)===0 && is_string($_POST['hosting_provider']??null)
            && HostingGuide::valid($_POST['hosting_provider'])) {
            $state['hosting_provider']=$_POST['hosting_provider'];
        }
        if (isset($_POST['show_guide']) && ($state['step']??0)===0) {
            // Fallback if JavaScript is disabled: update the host-specific guide.
        } elseif(isset($_POST['back']) && $state['step']>1 && ($state['authorized_until']??0)>=time()){
            $state['step']=($state['step']===4 && ($state['db']['driver']??'')==='sqlite')
                ? 2 : max(1,$state['step']-1);
        }
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
                        throw new InvalidArgumentException('Le fichier de vérification est introuvable ou incorrect. Dans les fichiers de Dendrila, ouvrez le dossier privé « config », à côté de « public », et déposez-y le fichier téléchargé, sans le renommer.');
                    }
                }elseif($method==='token'){
                    $record=is_file($directory.'/install-token.json')?json_decode((string)file_get_contents($directory.'/install-token.json'),true):null;
                    $token=is_string($_POST['token']??null)?$_POST['token']:'';
                    if(!is_array($record)||($record['expires']??0)<time()||!hash_equals((string)($record['hash']??''),hash('sha256',$token))){
                        throw new InvalidArgumentException('Clé incorrecte ou expirée. Vous pouvez aussi utiliser le fichier de preuve dans le gestionnaire de fichiers.');
                    }
                    $expires=min($expires,(int)$record['expires']);
                }else{throw new InvalidArgumentException('Méthode de vérification inconnue.');}
                session_regenerate_id(true);$state=['step'=>1,'authorized_until'=>$expires,'hosting_provider'=>$state['hosting_provider']??'other'];break;
            case 1:
                $theme=is_string($_POST['theme']??null)?$_POST['theme']:'';
                if(!isset(Catalog::themes()[$theme]))throw new InvalidArgumentException('Choisissez un thème.');
                $state['theme']=$theme;
                // Photo-first starters need a media library; show it preselected,
                // but always let the site owner opt out before installation.
                if(!isset($state['addons'])) {
                    $state['addons']=in_array($theme,['simple-illustrated','quiet-elegant','image-forward'],true)
                        ? ['media'] : [];
                }
                $state['step']=2;break;
            case 2:
                $state['addons']=Catalog::selection($_POST['addons']??[]);
                $requirements=Catalog::requirements($state['addons']);
                foreach($requirements['extensions'] as $ext)
                    if(!extension_loaded($ext))throw new InvalidArgumentException('Extension PHP à activer : '.$ext);
                $state['db']=['driver'=>'sqlite'];
                $state['secret']=$state['secret']??Totp::secret();
                // No external database connection step for local SQLite.
                $state['step']=4;break;
            case 3:
    $body.='<h2>Stockage local automatique</h2><p>SQLite sera créé dans le dossier privé du CMS. '
        .'Aucune base externe ni connexion SQL n’est nécessaire.</p>';break;
case 4:
                $account=[];foreach(['name','email','password','code','url'] as $key)$account[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
                Installer::install($state,$account,$directory);unset($_SESSION['install']);session_regenerate_id(true);
                echo App::page('Votre site est prêt','<p>Le thème et les modules choisis sont installés. Les pages inspirées du modèle du thème sont créées : seul l’accueil est publié, les autres attendent votre relecture dans les brouillons. Activez ensuite leurs liens de menu.</p><p><a class="button" href="/admin/">Ouvrir l’administration</a></p><p>Attendez le prochain code de votre application d’authentification pour vous connecter.</p>');exit;
        }
    }catch(InvalidArgumentException|DomainException $e){$error=$e->getMessage();http_response_code(422);}
    catch(Throwable $e){error_log('Dendrila installation: '.$e::class);$error='Installation interrompue. Consultez les journaux serveur. Si des tables ont été créées, utilisez une nouvelle base vide ; aucune donnée existante ne sera effacée.';http_response_code(500);}
}
$_SESSION['install']=$state;
$steps=['Accès','Thème','Modules','Administrateur'];
$visibleStep=($state['step']??0)===4?3:(int)($state['step']??0);
$body='<ol class="setup-steps" aria-label="Étapes de l’installation">';
foreach($steps as $i=>$label)$body.='<li'.($i===$visibleStep?' aria-current="step"':'').'><span class="setup-step-num">'.($i+1).'</span><span class="setup-step-label">'.App::h($label).'</span></li>';
$body.='</ol><p class="setup-progress-label">Étape '.($visibleStep+1).' sur '.count($steps).' — '.App::h($steps[$visibleStep]??'').'</p>';
if($error!=='')$body.='<p class="notice" role="alert">'.App::h($error).'</p>';
$body.='<form method="post"><input type="hidden" name="csrf" value="'.App::h($csrf->token()).'">';
$input=static fn(string $label,string $name,string $type='text',string $value=''):string=>'<label class="field">'.App::h($label).'<input required name="'.$name.'" type="'.$type.'" value="'.App::h($value).'"'.($type==='password'?' autocomplete="new-password"':'').'></label>';
switch($state['step']){
case 0:
    $provider=is_string($state['hosting_provider']??null)
        && HostingGuide::valid($state['hosting_provider'])
            ? $state['hosting_provider'] : 'other';
    $body.='<section class="setup-welcome"><h2>Bienvenue ! Préparons votre site.</h2>'
        .'<p><strong>Cette vérification est obligatoire si vous installez Dendrila '
        .'sur votre propre hébergement</strong> (o2switch, OVHcloud, Infomaniak, etc.). '
        .'Elle permet de confirmer que vous êtes autorisé à installer le site.</p>'
        .'<p>Vous ne gérez pas vous-même le compte de votre hébergeur ? '
        .'<strong>Demandez au gestionnaire du compte ou à la personne qui administre '
        .'votre hébergement</strong> de déposer le fichier de vérification à votre place. '
        .'Vous pouvez lui transmettre les instructions indiquées ci-dessous, '
        .'mais pas votre mot de passe.</p>'
        .'<p><strong>Vous n’avez besoin ni de programmer ni de saisir de commandes.</strong> '
        .'Choisissez votre hébergeur : nous vous guidons pas à pas.</p></section>';
    $body.=HostingGuide::render($provider);
    $body.='<section class="setup-proof" aria-labelledby="setup-proof-title">'
        .'<h2 id="setup-proof-title">Votre fichier de vérification</h2>'
        .'<p>Un petit fichier texte suffit pour prouver que vous pouvez accéder à vos fichiers privés.</p>'
        .'<a class="button setup-proof-download" href="/install.php?download_proof=1" download="'.WebOwnerProof::FILENAME.'">Télécharger le fichier prêt à déposer</a>'
        .'<p class="setup-proof-location">Une fois téléchargé, placez ce fichier dans le dossier <code>config</code> '
        .'de Dendrila, à côté du dossier <code>public</code>. <strong>Ne le mettez pas dans <code>public</code> '
        .'ou <code>public_html</code>.</strong></p>'
        .'<details class="setup-advanced"><summary>Je préfère créer le fichier moi-même</summary>'
        .'<p>Créez un fichier nommé <code>'.WebOwnerProof::FILENAME.'</code> '
        .'dans le dossier privé <code>config</code>, puis copiez ce code dedans :</p>'
        .'<div class="setup-copy-row"><code class="secret" id="ownership-challenge">'
        .App::h($state['ownership_challenge']).'</code>'
        .'<button type="button" id="copy-ownership-proof" class="button secondary">Copier le code</button></div>'
        .'<p id="copy-proof-status" class="setup-feedback" role="status" aria-live="polite"></p></details>'
        .'<p class="setup-help">Une fois le fichier placé dans <code>config</code>, cliquez sur '
        .'« Vérifier et continuer ». Dendrila supprimera automatiquement ce fichier après vérification.</p>'
        .'<p><a href="/preflight.php">Vérifier la compatibilité de mon hébergement</a></p></section>'
        .'<details class="setup-advanced setup-terminal"><summary>Option avancée : utiliser une clé de préparation</summary>'
        .'<p>Cette méthode s’adresse aux personnes ayant déjà préparé Dendrila avec un terminal.</p>'
        .'<label class="field">Clé de préparation (facultative)<input name="token" type="password" autocomplete="off"></label>'
        .'<button class="button secondary" name="auth_method" value="token">Continuer avec la clé</button>'
        .'</details>';

    break;
case 1:
    $body.='<h2>Quelle ambiance pour votre site ?</h2><p>Découvrez <strong>six compositions différentes</strong> : services, maison d’hôtes, portfolio photo, studio minimal, événement et média éditorial. Chaque thème installe une <strong>structure complète de pages et de sections modifiables</strong>. Seul l’accueil est publié ; les autres pages sont créées en brouillons et restent absentes du menu public avant validation. Aucun thème n’impose un blog. Les images des démonstrations sont locales et ne sont pas automatiquement ajoutées à vos contenus. Aucun aperçu ne modifie votre sélection.</p><div class="theme-grid">';
    foreach(Catalog::themes() as $id=>$theme){
        $label=App::h($theme['label']);
        $preview='/theme-preview.php?theme='.rawurlencode($id);
        $body.='<article class="theme-card">'
            .'<label class="theme-option"><span class="theme-preview">'
            .'<iframe title="Démonstration complète : '.$label.'" src="'.$preview.'&amp;mode=demo" tabindex="-1" loading="lazy" sandbox></iframe></span>'
            .'<span class="theme-choice"><input required type="radio" name="theme" value="'.App::h($id).'"'
            .(($state['theme']??'')===$id?' checked':'').'> <strong>'.$label.'</strong></span>'
            .'<p>'.App::h($theme['description']).'</p></label>'
            .'<div class="theme-card__actions">'
            .'<a href="'.$preview.'&amp;mode=demo" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir la démonstration complète, thème '.$label.'">Voir la démonstration <span aria-hidden="true">↗</span></a>'
            .'<a href="'.$preview.'&amp;mode=empty" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir le site de départ, thème '.$label.'">Pages de départ <span aria-hidden="true">↗</span></a>'
            .'</div></article>';
    }
    $body.='</div>';break;
case 2:
    $body.='<h2>De quoi avez-vous besoin ?</h2><p>Pages, menu et administration sont inclus. La médiathèque est précochée pour les thèmes illustrés, mais vous pouvez la décocher.</p>'
        .'<p class="notice"><strong>Stockage local automatique :</strong> pour ce site, Dendrila utilisera SQLite dans son dossier privé. Vous n’avez aucune base externe à créer ni aucun identifiant SQL à fournir.</p>';
    foreach(Catalog::addons() as $id=>$module)$body.='<label class="addon-card"><input type="checkbox" name="addons[]" value="'.$id.'"'.(in_array($id,$state['addons']??[],true)?' checked':'').'><span><strong>'.App::h($module['label']).'</strong><br>'.App::h($module['description']).'</span></label>';
    $body.='<div class="addon-card addon-card--unavailable" role="group" aria-labelledby="booking-addon-title">'
        .'<label><input type="checkbox" disabled aria-describedby="booking-addon-status"> '
        .'<strong id="booking-addon-title">Réservations (Booking)</strong></label>'
        .'<span>Créneaux, disponibilités, synchronisation calendrier et paiements : le module Dendrila Booking '
        .'existe, mais son intégration dans cet installateur n’est pas terminée. '
        .'Aucune réservation ni transaction ne sera activée par ce choix.</span>'
        .'<span id="booking-addon-status" class="setup-addon-status">En cours d’intégration — non disponible</span></div>';
    break;
case 3:
    $body.='<h2>Relier votre base de données</h2><ul>';foreach(Catalog::requirements($state['addons'])['database_reasons'] as $reason)$body.='<li>'.App::h($reason).'</li>';
    $body.='</ul><p>Une base MariaDB vide suffit. Voici comment la préparer :</p>'
        .HostingGuide::databaseGuide((string)($state['hosting_provider']??'other'))
        .'<p>Complétez les informations indiquées par votre hébergeur :</p>'.$input('Serveur','host','text',$state['db']['host']??'localhost').$input('Port','port','number',$state['db']['port']??'3306').$input('Nom de la base','name','text',$state['db']['name']??'').$input('Utilisateur de la base','user','text',$state['db']['user']??'').$input('Mot de passe de la base','password','password');break;
case 4:
    $body.='<h2>Votre site et votre compte</h2><p><strong>Votre stockage SQLite sera créé automatiquement.</strong> Vous n’avez aucun raccordement MariaDB à effectuer.</p>'.$input('Nom du site','name').$input('Adresse HTTPS du site','url','url').$input('Votre e-mail','email','email').$input('Votre mot de passe (12 caractères minimum)','password','password').'<h3>Protéger votre compte</h3><p>Ajoutez un compte dans votre application d’authentification : clé ci-dessous, codes à 6 chiffres, période de 30 secondes.</p><p><code class="secret">'.App::h($state['secret']).'</code></p><p>Conservez cette clé dans votre gestionnaire de mots de passe : elle permet de rétablir votre second facteur.</p>'.$input('Code actuel à 6 chiffres','code');break;
}
$body.='<div class="setup-actions">';if($state['step']>1)$body.='<button class="button secondary" name="back" value="1" formnovalidate>Retour</button>';
if($state['step']===0){
    $body.='<button class="button" name="auth_method" value="file">Vérifier et continuer</button>';
}else{
    $body.='<button class="button">'.($state['step']===4?'Installer mon site':'Continuer').'</button>';
}
$body.='</div></form>';
$body.='<script src="/assets/install-help.js?v=1" defer></script>';
echo App::page('Installons votre site',$body);

