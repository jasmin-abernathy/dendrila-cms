<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
use Dendrila\Cms\Installation\Catalog;
App::session();
try{
    $pdo=App::database();$actor=App::identity($pdo)->currentActor();
    if(!$actor){header('Location: /admin/login.php',true,303);exit;}
    if($actor->siteId!==App::site()||getenv('DENDRILA_BOOKING_SETUP_REQUESTED')!=='1'){
        http_response_code(404);exit;
    }
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex,nofollow');
    $mode=getenv('DENDRILA_BOOKING_MODE')?:'';
    $options=Catalog::bookingModes();
    $modeName=$options[$mode]['label']??'Mode non défini';
    $appointments=in_array($mode,['appointments','both'],true);
    $payments=in_array($mode,['payments','both'],true);
    $body='<div style="max-width:56rem;margin:2rem auto;padding:1.5rem;line-height:1.65">'
        .'<p><a href="/admin/">← Retour à l’administration</a></p>'
        .'<h2>Booking : configuration en attente</h2>'
        .'<p><strong>Usage sélectionné : '.App::h($modeName).'.</strong></p>'
        .'<p><strong>Stockage : SQLite privé.</strong> Les fonctionnalités restent désactivées tant que le module n’est pas adapté, raccordé et testé.</p>'
        .'<h3>Avant activation</h3><ol>'
        .'<li>Installer le module approuvé hors du dossier public et appliquer son schéma SQLite validé.</li>'
        .'<li>Raccorder routes, permissions et CSRF à la session Dendrila.</li>';
    if($appointments)$body.='<li>Tester les créneaux, les verrous transactionnels SQLite et les réservations concurrentes. Configurer les horaires et éventuellement Google Calendar.</li>';
    if($payments)$body.='<li>Configurer les prestataires en mode test et vérifier les commandes ponctuelles sans réservation obligatoire.</li>';
    $body.='<li>Tester les signatures des webhooks, les notifications et l’idempotence avant toute activation publique.</li></ol>'
        .'<p><strong>Statut : à configurer.</strong> Aucun paiement ou webhook n’est activé par ce panneau.</p></div>';
    echo App::page('Booking — configuration en attente',$body);
}catch(Throwable $e){error_log('Booking setup page: '.$e::class);http_response_code(503);}
