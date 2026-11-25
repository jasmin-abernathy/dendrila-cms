<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalAppearance;

require __DIR__ . '/portal-view.php';

$selectors = ['page' => 'site', 'banner' => 'pub', 'title' => 'enseigne', 'menu' => 'menu',
    'intro' => 'ouverture', 'news' => 'direct', 'agenda' => 'agenda', 'archive' => 'archives', 'focus' => 'droite'];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?= $t('name') ?> — <?= $t('tagline') ?></title>
<link rel="stylesheet" type="text/css" href="<?= $escape($theme['stylesheet']) ?>">
<style type="text/css">
<?php foreach (PortalAppearance::ZONES as $key => [, , , $width, $height]): ?>
#<?= $selectors[$key] ?><?= $key === 'menu' ? ' a' : '' ?> { <?= $format($key) ?>; }
<?php if ($width > 0): ?>
.zone-<?= $key ?> img.illustration { max-width:<?= $width ?>px; max-height:<?= $height ?>px; }
<?php endif ?>
<?php endforeach ?>
<?php if ($backgroundCss !== ''): ?>
body, #site { <?= $backgroundCss ?> }
<?php endif ?>
</style>
</head>
<body>
<div id="site" class="zone-page">
<?php if ($preview): ?><p class="preview"><strong>Aperçu du brouillon</strong> | <a href="oldweb-editor.php">Retour aux réglages</a></p><?php endif ?>
<div id="pub" class="zone-banner"><?= $image('banner') ?><a href="#agenda"><?= $t('banner') ?></a></div>
<h1 id="enseigne" class="zone-title site-name"><?= $t('name') ?></h1>
<p id="sous-titre" class="site-tagline"><?= $t('tagline') ?></p>
<div id="menu" class="zone-menu">
<ul>
<li><a href="#ouverture">Accueil</a></li>
<li><a href="#direct">À l’antenne</a></li>
<li><a href="#agenda">Agenda</a></li>
<li><a href="#archives">Archives</a></li>
<li><a href="#contact">Nous écrire</a></li>
</ul>
<p class="etat"><?= $t('status') ?></p>
</div>
<div id="droite" class="zone-focus">
<h2><?= $t('focusTitle') ?></h2>
<?= $image('focus') ?>
<p><?= $t('focusText') ?></p>
<h2>Sur le site</h2>
<p><a href="#direct">L’émission du mois</a><br><a href="#agenda">Les prochaines dates</a><br><a href="#archives">Les anciennes bandes</a></p>
<h2 id="contact">Courrier</h2>
<p><?= $t('contact') ?></p>
</div>
<div id="contenu">
<div id="ouverture" class="zone-intro">
<?= $image('intro') ?>
<p class="frequence"><?= $t('frequency') ?></p>
<h2><?= $t('introTitle') ?></h2>
<p><?= $t('introText') ?></p>
</div>
<div id="direct" class="sujet zone-news">
<div class="pastille"><?= $image('news') ?: 'SUR<br>LES ONDES' ?></div>
<div class="texte"><h2><?= $t('newsTitle') ?></h2><p><?= $t('newsText') ?></p><p><a href="#agenda">Voir l’heure de la diffusion</a></p></div>
</div>
<div id="agenda" class="zone-agenda">
<h2>Agenda du club</h2>
<?= $image('agenda') ?>
<ul><li><?= $t('agendaFirst') ?></li><li><?= $t('agendaSecond') ?></li></ul>
</div>
<div id="archives" class="sujet zone-archive">
<div class="pastille"><?= $image('archive') ?: 'BANDES<br>ARCHIVES' ?></div>
<div class="texte"><h2><?= $t('archiveTitle') ?></h2><p><?= $t('archiveText') ?></p></div>
</div>
</div>
<div id="pied"><span class="site-name"><?= $t('name') ?></span> &copy; <?= $t('year') ?> &nbsp; | &nbsp; <a href="#enseigne">Haut de page</a>
<?php if ($hasGif): ?> &nbsp; | &nbsp; <a href="<?= $preview ? '?preview=1' : '?page=1' ?><?= $still ? '' : '&amp;still=1' ?>"><?= $still ? 'Afficher les GIF' : 'Masquer les GIF' ?></a><?php endif ?></div>
</div>
</body>
</html>
