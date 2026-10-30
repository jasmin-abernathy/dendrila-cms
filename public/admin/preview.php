<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\{Application as App,Presentation};
App::session();$pdo=App::database();$actor=App::identity($pdo)->currentActor();
if(!$actor){http_response_code(401);exit('Connexion nécessaire.');}
try{
    $store=new \Dendrila\Cms\Storage\PdoCmsStore($pdo);
    $page=(new \Dendrila\Cms\Content\ContentService($store,new \Dendrila\Cms\Authorization\AuthorizationService($store)))->preview($actor,is_string($_GET['id']??null)?$_GET['id']:'');
    header('X-Robots-Tag: noindex, nofollow');header('Content-Type: text/html; charset=utf-8');
    echo (new \Dendrila\Cms\Front\PublicPageRenderer())->render(['name'=>getenv('DENDRILA_SITE_NAME')?:'Dendrila'],$page['values']??$page,Presentation::forTheme(getenv('DENDRILA_THEME')?:'quiet-elegant'),'/assets/site.css',\Dendrila\Cms\Media\MediaFactory::fromEnvironment($pdo)?->publicMap(App::site())??[],[],Presentation::consentFor($page['values']??$page));
}catch(\Dendrila\Cms\Authorization\AccessDenied){http_response_code(403);echo 'Aperçu interdit.';}
catch(\Dendrila\Cms\Content\ContentNotFound){http_response_code(404);echo 'Page introuvable.';}
catch(InvalidArgumentException){http_response_code(422);echo App::page('Aperçu incomplet','<p>Complétez les champs et les photographies des blocs avant publication.</p>');}
