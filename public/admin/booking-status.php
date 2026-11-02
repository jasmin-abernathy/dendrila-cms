<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use Dendrila\Cms\Runtime\Application as App;
App::session();
try{
    $pdo=App::database();$actor=App::identity($pdo)->currentActor();
    if(!$actor){header('Location: /admin/login.php',true,303);exit;}
    if($actor->siteId!==App::site()||getenv('DENDRILA_BOOKING_SETUP_REQUESTED')!=='1'){
        http_response_code(404);exit;
    }
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex,nofollow');
    $body='<div style="max-width:56rem;margin:2rem auto;padding:1.5rem;line-height:1.65">'
        .'<p><a href="/admin/">← Retour à l’administration</a></p>'
        .'<h2>Booking : configuration en attente</h2>'
        .'<p>Votre choix a été enregistré. Les réservations et paiements sont désactivés tant que le module serveur n’est pas installé et testé.</p>'
        .'<h3>Prérequis avant activation</h3>'
        .'<ol><li>Installer le module Bookings approuvé hors de la racine publique.</li>'
        .'<li>Créer une base MySQL/MariaDB dédiée, distincte du SQLite du CMS.</li>'
        .'<li>Raccorder ses routes et permissions à votre session Dendrila.</li>'
        .'<li>Configurer SMTP, Google Calendar et paiements en environnement de test.</li>'
        .'<li>Valider toutes les étapes avant d’activer publiquement la réservation.</li></ol>'
        .'<p><strong>Statut : à configurer.</strong> Aucun paiement ou webhook n’est activé par ce panneau.</p></div>';
    echo App::page('Booking — configuration en attente',$body);
}catch(Throwable $e){error_log('Booking setup page: '.$e::class);http_response_code(503);}
