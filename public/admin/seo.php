<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Seo\{PdoSeoRepository,SeoService,SeoMetadataDefinition,SeoKeywordSettings,SeoWritingAdvisor};

App::session();
try{
    $pdo=App::database();$identity=App::identity($pdo);$actor=$identity->currentActor();
    if(!$actor){header('Location: /admin/login.php',true,303);exit;}
    $store=new PdoCmsStore($pdo);
    if(!$store->actorHasPermission($actor->siteId,$actor->actorId,'seo.manage')
       ||!$store->actorHasPermission($actor->siteId,$actor->actorId,'content.read')){
        http_response_code(403);exit('Accès au référencement non autorisé.');
    }
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Robots-Tag: noindex,nofollow');
    $csrf=new SessionCsrf();
    $service=new SeoService(new PdoSeoRepository($pdo),$store,new AuthorizationService($store));
    $siteId=App::site();
    $fetchPages=static function()use($pdo,$siteId):array {
        $q=$pdo->prepare("SELECT content_id,slug,status,values_json,published_values_json FROM contents WHERE site_id=? AND type_id='page' AND status<>'archived' ORDER BY CASE WHEN slug='home' THEN 0 ELSE 1 END, slug LIMIT 500");
        $q->execute([$siteId]);$out=[];
        while($r=$q->fetch(PDO::FETCH_ASSOC)){
            $values=json_decode((string)$r['values_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($values))continue;
            $r['values']=$values;$out[$r['content_id']]=$r;
        }
        return $out;
    };
    $pages=$fetchPages();
    $selected=is_string($_GET['page']??null)?$_GET['page']:'';
    if($selected===''&&$pages!==[])$selected=(string)array_key_first($pages);
    if($selected!==''&&!isset($pages[$selected]))$selected=(string)(array_key_first($pages)??'');
    $error='';$notice='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        try{
            if(!$csrf->verify(is_string($_POST['csrf']??null)?$_POST['csrf']:''))throw new InvalidArgumentException('Formulaire expiré.');
            $action=$_POST['action']??null;
            if(!is_string($action))throw new InvalidArgumentException('Action inconnue.');
            $settings=SeoKeywordSettings::read($pdo,$siteId);
            if($action==='save_topics'){
                $input=$_POST['topics']??null;
                if(!is_string($input)||strlen($input)>5000)throw new InvalidArgumentException('Liste de mots-clés invalide.');
                SeoKeywordSettings::save($pdo,$siteId,(string)($_POST['settings_hash']??''),
                    SeoKeywordSettings::normalize($input),$settings['pages']);
                header('Location: /admin/seo.php?saved=topics'.($selected!==''?'&page='.rawurlencode($selected):''),true,303);exit;
            }
            if($action!=='save_page')throw new InvalidArgumentException('Action inconnue.');
            $pageId=$_POST['page_id']??null;
            if(!is_string($pageId)||!isset($pages[$pageId]))throw new InvalidArgumentException('Page inconnue.');
            $version=filter_var($_POST['version']??null,FILTER_VALIDATE_INT);
            if($version===false||$version<0)throw new InvalidArgumentException('Version SEO invalide.');
            $rawTerms=$_POST['page_topics']??null;
            if(!is_string($rawTerms)||strlen($rawTerms)>1000)throw new InvalidArgumentException('Sujets de page invalides.');
            $pageTerms=SeoKeywordSettings::normalize($rawTerms,5);
            $metadata=SeoMetadataDefinition::normalize([
                'title'=>$_POST['seo_title']??'',
                'description'=>$_POST['seo_description']??'',
                'canonical_url'=>$_POST['canonical_url']??'',
                'social_title'=>null,'social_description'=>null,'social_image_url'=>null,
                'indexable'=>isset($_POST['indexable']),
            ]);
            // Lock against stale changes before either write.
            $oldSeo=$service->get($actor,$pageId);
            if((int)($oldSeo['version']??0)!==$version)
                throw new DomainException('Cette page a été modifiée dans un autre onglet.');
            if(!hash_equals(hash('sha256',$settings['raw']),(string)($_POST['settings_hash']??'')))
                throw new DomainException('La liste de mots-clés a changé dans un autre onglet.');
            // SEO metadata uses the existing audited, versioned service. Topic
            // assignments remain independently stored and are never added to
            // draft/published content or auto-rewritten by this editor.
            $service->save($actor,$pageId,$metadata,$version);
            $pageLists=$settings['pages'];$pageLists[$pageId]=$pageTerms;
            SeoKeywordSettings::save($pdo,$siteId,(string)$_POST['settings_hash'],
                $settings['keywords'],$pageLists);
            header('Location: /admin/seo.php?saved=page&page='.rawurlencode($pageId),true,303);exit;
        }catch(InvalidArgumentException|DomainException $e){
            http_response_code(422);$error=$e->getMessage();
        }
    }
    $settings=SeoKeywordSettings::read($pdo,$siteId);
    $record=$selected!==''?$pages[$selected]:null;
    $seo=$selected!==''?$service->get($actor,$selected):null;
    $page=$record['values']??null;
    $selectedTerms=$record?SeoKeywordSettings::keywordsForPage($settings,$selected,$page):[];
    $advice=$record?SeoWritingAdvisor::review($page,$seo??[],$selectedTerms):['checks'=>[],'total'=>0];
    $h=static fn(mixed $s):string=>App::h($s);
    $siteTopicText=implode("\n",$settings['keywords']);
    $pageTopicText=implode("\n",$selectedTerms);
    if(isset($_GET['saved']))$notice='Enregistrement effectué. Les métadonnées seront servies avec la page publique.';
    $body='<div class="seo-editor"><p class="seo-back"><a href="/admin/?section=tableau">← Administration</a></p>';
    $body.='<p class="seo-kicker">Référencement · Dendrila</p><h2>SEO du site</h2>';
    $body.='<p class="seo-intro">Définissez vos sujets principaux et travaillez le référencement page par page, sans modifier automatiquement les textes.</p>';
    if($error!=='')$body.='<p class="seo-error" role="alert">'.$h($error).'</p>';
    if($notice!=='')$body.='<p class="seo-success" role="status">'.$h($notice).'</p>';
    $body.='<section class="seo-panel"><h3>1. Sujets du site</h3>';
    $body.='<p>Ajoutez les expressions qui décrivent vraiment votre activité, une par ligne (25 maximum). Elles aident à choisir les sujets des pages et ne sont pas ajoutées partout sans pertinence.</p>';
    $body.='<form method="post" action="/admin/seo.php'.($selected!==''?'?page='.$h(rawurlencode($selected)):'').'">';
    $body.='<input type="hidden" name="csrf" value="'.$h($csrf->token()).'"><input type="hidden" name="action" value="save_topics">';
    $body.='<input type="hidden" name="settings_hash" value="'.hash('sha256',$settings['raw']).'">';
    $body.='<label for="seo-site-topics">Mots-clés principaux</label>';
    $body.='<textarea id="seo-site-topics" name="topics" rows="4" maxlength="5000" placeholder="services administratifs&#10;gestion de documents&#10;accompagnement administratif">'.$h($siteTopicText).'</textarea>';
    $body.='<p class="seo-help">Ces mots-clés servent d’abord à la rédaction. Google n’utilise pas la balise meta keywords pour classer les pages.</p>';
    $body.='<button class="button" type="submit">Enregistrer les sujets</button></form></section>';
    $body.='<section class="seo-panel"><h3>2. Référencement de chaque page</h3>';
    if($record===null){
        $body.='<p>Aucune page n’est encore disponible. Créez une page dans l’éditeur de contenus.</p>';
    }else{
        $body.='<form method="get" action="/admin/seo.php" class="seo-page-chooser">';
        $body.='<label for="seo-page-select">Page à analyser</label><select id="seo-page-select" name="page">';
        foreach($pages as $id=>$entry){
            $label=$entry['slug']==='home'?'Page d’accueil':(string)($entry['values']['title']??$entry['slug']);
            $body.='<option value="'.$h($id).'"'.($id===$selected?' selected':'').'>'.$h($label).'</option>';
        }
        $body.='</select><button type="submit" class="button secondary">Afficher</button></form>';
        $body.='<div class="seo-grid"><div class="seo-form"><form method="post" action="/admin/seo.php?page='.$h(rawurlencode($selected)).'">';
        $body.='<input type="hidden" name="csrf" value="'.$h($csrf->token()).'"><input type="hidden" name="action" value="save_page">';
        $body.='<input type="hidden" name="page_id" value="'.$h($selected).'"><input type="hidden" name="version" value="'.(int)($seo['version']??0).'">';
        $body.='<input type="hidden" name="settings_hash" value="'.hash('sha256',$settings['raw']).'">';
        $body.='<div class="seo-field"><label for="seo-page-topics">Mots-clés ciblés sur cette page</label>';
        $body.='<textarea id="seo-page-topics" name="page_topics" rows="3" maxlength="1000" placeholder="Un sujet principal par ligne, cinq maximum">'.$h($pageTopicText).'</textarea>';
        $body.='<small>Choisissez uniquement les expressions réellement liées à cette page. Les mots-clés sont injectés côté serveur dans la balise historique meta keywords, sans surcharger les autres pages.</small></div>';
        $body.='<div class="seo-field"><label for="seo-page-title">Titre pour les moteurs</label>';
        $body.='<input id="seo-page-title" type="text" name="seo_title" maxlength="190" value="'.$h($seo['title']??'').'" placeholder="'.$h($page['title']??'').'" data-seo-title>';
        $body.='<small>Laissez vide pour reprendre le titre de la page.</small></div>';
        $body.='<div class="seo-field"><label for="seo-page-description">Description dans les résultats</label>';
        $body.='<textarea id="seo-page-description" name="seo_description" maxlength="500" rows="4" data-seo-description>'.$h($seo['description']??'').'</textarea>';
        $body.='<small>Une phrase informative et spécifique à la page, plutôt qu’un catalogue de mots-clés.</small></div>';
        $body.='<div class="seo-field"><label for="seo-canonical">URL canonique (facultative)</label>';
        $body.='<input id="seo-canonical" type="text" maxlength="2048" name="canonical_url" placeholder="/'.$h($record['slug']==='home'?'':$record['slug']).'" value="'.$h($seo['canonical_url']??'').'">';
        $body.='<small>Chemin interne commençant par / ou URL HTTPS.</small></div>';
        $body.='<label class="seo-check"><input type="checkbox" name="indexable" value="1"'.(($seo['indexable']??true)?' checked':'').'> Autoriser l’indexation de cette page</label>';
        $body.='<p><button class="button" type="submit">Enregistrer le SEO de cette page</button></p></form></div>';
        $body.='<aside class="seo-advice"><h4>Conseils de rédaction</h4><p class="seo-help">Analyse automatique des textes et des mots-clés sélectionnés. Aucun service externe ni modification du contenu.</p><ul>';
        foreach($advice['checks'] as $check){
            $ico=$check['status']==='ok'?'✓':($check['status']==='info'?'ℹ':'!');
            $body.='<li class="seo-tip seo-tip--'.$h($check['status']).'"><span class="seo-tip-icon" aria-hidden="true">'.$ico.'</span><div><strong>'.$h($check['label']).'</strong><p>'.$h($check['detail']).'</p></div></li>';
        }
        $body.='</ul></aside></div>';
        $body.='<p class="seo-help">Aucun score de « densité » : la répétition excessive de mots-clés nuit à la lisibilité. Les conseils sont indicatifs et ne garantissent aucun classement.</p>';
    }
    $body.='</section></div>';
    $html=App::page('SEO',$body);
    echo str_replace('<body>','<link rel="stylesheet" href="/backoffice-assets/seo.css?v=1"><body>',$html);
}catch(Throwable $e){
    error_log('Dendrila SEO admin: '.$e::class.' '.$e->getMessage());
    http_response_code(503);echo App::page('SEO indisponible','<p>Vérifiez les journaux du serveur.</p>');
}
