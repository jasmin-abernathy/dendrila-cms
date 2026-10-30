<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\{Application as App,AppearanceSettings};
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Storage\PdoCmsStore;

App::session();
try {
    $pdo=App::database();
    $actor=App::identity($pdo)->currentActor();
    if(!$actor){header('Location: /admin/login.php',true,303);exit;}
    $store=new PdoCmsStore($pdo);
    if(!$store->actorHasPermission($actor->siteId,$actor->actorId,'users.manage')){
        http_response_code(403);exit('Réglages réservés aux administrateurs.');
    }
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' https: data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Robots-Tag: noindex, nofollow');
    $csrf=new SessionCsrf();
    $message='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{
            if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new \InvalidArgumentException('Formulaire expiré.');
            $theme=$_POST['theme']??null;
            if(!is_string($theme))throw new \InvalidArgumentException('Thème invalide.');
            $enabled=$_POST['enabled']??[];
            if(!is_array($enabled)||count($enabled)>count(AppearanceSettings::COLORS)
              ||array_diff($enabled,array_keys(AppearanceSettings::COLORS))!==[])
              throw new \InvalidArgumentException('Choix de couleurs invalide.');
            $brand=[];
            foreach(array_keys(AppearanceSettings::COLORS) as $key){
                if(!in_array($key,$enabled,true))continue;
                $value=$_POST['color'][$key]??null;
                if(!is_string($value)||!preg_match('/^#[0-9a-fA-F]{6}$/D',$value))
                  throw new \InvalidArgumentException('Couleur invalide.');
                $brand[$key]=$value;
            }
            foreach(['heading_font','body_font'] as $key){
                $value=$_POST[$key]??'';
                if(!is_string($value)||strlen($value)>40)throw new \InvalidArgumentException('Police invalide.');
                if($value!=='')$brand[$key]=$value;
            }
            foreach(AppearanceSettings::ASSETS as $key=>$label){
                $value=$_POST[$key]??'';
                if(!is_string($value)||strlen($value)>2048)throw new \InvalidArgumentException('URL de média invalide.');
                $value=trim($value);
                if($value!=='')$brand[$key]=$value;
            }
            $version=$_POST['version']??null;
            AppearanceSettings::save($pdo,App::site(),is_string($version)?$version:'',$theme,$brand);
            header('Location: /admin/appearance.php?saved=1',true,303);
            exit;
        }catch(\InvalidArgumentException|\DomainException $e){
            http_response_code(422);
            $message='<p role="alert">'.App::h($e->getMessage()).'</p>';
        }
    }
    $state=AppearanceSettings::read($pdo,App::site());
    $body='<p><a href="/admin/">← Administration</a></p>';
    $body.='<p>Personnalisez les six thèmes natifs sans toucher aux fichiers CSS ni importer de polices. Les sites utilisant leur propre modèle HTML, comme Prestadmin, conservent leur identité existante.</p>';
    if(isset($_GET['saved']))$message='<p role="status">Apparence enregistrée. Les pages publiques natives et leurs aperçus utilisent désormais ces paramètres.</p>';
    $body.=$message;
    if(getenv('DENDRILA_BRAND_JSON')!==false)$body.='<p role="note">La variable serveur DENDRILA_BRAND_JSON peut remplacer certaines valeurs enregistrées ici.</p>';
    $body.='<form method="post" action="/admin/appearance.php"><input type="hidden" name="csrf" value="'.App::h($csrf->token()).'"><input type="hidden" name="version" value="'.hash('sha256',$state['raw']).'">';
    $body.='<fieldset><legend>Thème du site</legend><label class="field">Direction visuelle<select name="theme" required>';
    foreach(AppearanceSettings::THEMES as $key=>$label){
        $body.='<option value="'.App::h($key).'"'.($state['theme']===$key?' selected':'').'>'.App::h($label).'</option>';
    }
    $body.='</select></label><p>Les thèmes sont locaux et versionnés. Le contenu, les blocs et la navigation sont conservés lorsque le style change.</p></fieldset>';
    $body.='<fieldset><legend>Palette de marque</legend><p>Cochez une couleur pour remplacer la valeur du thème ; décochez-la pour revenir au thème. Les contrastes texte/fond sélectionnés sont vérifiés.</p>';
    foreach(AppearanceSettings::COLORS as $key=>$label){
        $custom=array_key_exists($key,$state['brand']);
        $body.='<label class="field"><input type="checkbox" name="enabled[]" value="'.App::h($key).'"'.($custom?' checked':'').'> '.App::h($label);
        $body.=' <input type="color" name="color['.App::h($key).']" value="'.App::h($custom?$state['brand'][$key]:'#777777').'" aria-label="'.App::h($label).'"></label>';
    }
    $body.='</fieldset><fieldset><legend>Typographies sans téléchargement</legend>';
    foreach(['heading_font'=>'Titres','body_font'=>'Corps de texte'] as $key=>$label){
        $body.='<label class="field">'.App::h($label).'<select name="'.App::h($key).'"><option value="">Police du thème (défaut)</option>';
        foreach(AppearanceSettings::FONTS as $value=>$name){
            $body.='<option value="'.App::h($value).'"'.(($state['brand'][$key]??'')===$value?' selected':'').'>'.App::h($name).'</option>';
        }
        $body.='</select></label>';
    }
    $body.='</fieldset><fieldset><legend>Logo et logotype</legend><p>Utilisez une URL HTTPS vers un média déjà publié, idéalement hébergé sur votre propre domaine. Aucun transfert automatique de fichier tiers.</p>';
    foreach(AppearanceSettings::ASSETS as $key=>$label){
        $body.='<label class="field">'.App::h($label).'<input type="url" inputmode="url" name="'.App::h($key).'" maxlength="2048" placeholder="https://…" value="'.App::h($state['brand'][$key]??'').'"></label>';
    }
    $body.='</fieldset><button class="button" type="submit">Enregistrer l’apparence</button> <a href="/" target="_blank" rel="noopener noreferrer">Voir le site</a></form>';
    echo App::page('Apparence du site',$body);
}catch(\Throwable $e){
    error_log('Dendrila appearance: '.$e::class.': '.$e->getMessage());
    http_response_code(503);echo App::page('Apparence indisponible','<p>Consultez les journaux du serveur.</p>');
}
