<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\BackOffice\{Config,CoreData,Kernel,SessionCsrf,NativeMediaPort};
use Dendrila\Cms\Navigation\{NavigationService,PdoNavigationRepository};
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Storage\PdoCmsStore;
try{
    App::session();$pdo=App::database();$identity=App::identity($pdo);
    if(!$identity->currentActor()){header('Location: /admin/login.php',true,303);exit;}
    $store=new PdoCmsStore($pdo);$media=\Dendrila\Cms\Media\MediaFactory::fromEnvironment($pdo);
    $kernel=new Kernel(new Config(siteId:App::site(),siteName:getenv('DENDRILA_SITE_NAME')?:'Dendrila'),$identity,new SessionCsrf(),new CoreData($store,new NavigationService(new PdoNavigationRepository($pdo),$store,new AuthorizationService($store))),$media?new NativeMediaPort($media):null);
    $result=$kernel->handle($_SERVER['REQUEST_METHOD'],$_GET,$_POST,$_FILES);
    http_response_code($result->status);foreach($result->headers as $k=>$v)header($k.': '.$v);echo $result->body;
}catch(Throwable $e){error_log('Dendrila admin: '.$e::class.': '.$e->getMessage());http_response_code(503);echo App::page('Administration indisponible','<p>Vérifiez la configuration du site et les journaux serveur.</p>');}
