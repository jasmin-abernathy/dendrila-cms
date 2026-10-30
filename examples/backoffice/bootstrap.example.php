<?php
declare(strict_types=1);

/**
 * EXEMPLE NON EXPOSÉ À HTTP.
 * À adapter dans le DOCUMENT ROOT d'un site après la migration de ses comptes.
 * Ne pas lier une session sans double authentification au CMS.
 */
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\BackOffice\Config;
use Dendrila\Cms\BackOffice\CoreData;
use Dendrila\Cms\BackOffice\IdentityProvider;
use Dendrila\Cms\BackOffice\Kernel;
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Navigation\NavigationService;
use Dendrila\Cms\Navigation\PdoNavigationRepository;
use Dendrila\Cms\Storage\PdoCmsStore;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Ce fichier doit exister HORS webroot. Son rôle : charger la configuration,
// vérifier la session + double authentification et fournir la base du site.
// La variable $verifiedIdentity DOIT être de type IdentityProvider.
// La variable $pdo DOIT être un PDO configuré pour les tables Dendrila.
// Aucune identité n'est tirée des paramètres GET/POST ni de cookies non vérifiés.
$privateBootstrap = getenv('DENDRILA_ADMIN_PRIVATE_BOOTSTRAP');
if (!is_string($privateBootstrap) || $privateBootstrap==='' || !is_file($privateBootstrap)) {
    http_response_code(503);
    exit('Administration non configurée.');
}
require $privateBootstrap;
if (!isset($verifiedIdentity, $pdo, $siteId, $siteName)
    || !$verifiedIdentity instanceof IdentityProvider || !$pdo instanceof PDO
    || !is_string($siteId) || !is_string($siteName)
    || session_status()!==PHP_SESSION_ACTIVE) {
    http_response_code(503);
    exit('Administration non configurée.');
}
$store=new PdoCmsStore($pdo);
$authorization=new AuthorizationService($store);
$navigation=new NavigationService(new PdoNavigationRepository($pdo),$store,$authorization);
$config=new Config(
    siteId:$siteId,
    siteName:$siteName,
    basePath:'/admin/',
    assetsPath:'/backoffice-assets',
    primaryColor:'#47755d',
    iconUrl:'/favicon.ico',
    loginUrl:'/admin/login.php',
);
// Raccordements facultatifs, après vérification complète de leurs permissions :
// $mediaPort = ...; // implements Dendrila\Cms\BackOffice\MediaPort
// $usersPort = ...; // implements Dendrila\Cms\BackOffice\UsersPort
$kernel=new Kernel($config,$verifiedIdentity,new SessionCsrf(),
    new CoreData($store,$navigation),
    $mediaPort??null,$usersPort??null
);
$result=$kernel->handle($_SERVER['REQUEST_METHOD'],$_GET,$_POST,$_FILES);
http_response_code($result->status);
foreach($result->headers as $name=>$value) header($name.': '.$value);
echo $result->body;
