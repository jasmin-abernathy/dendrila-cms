<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalAppearance;

require __DIR__ . '/portal-view.php';

$selectors = ['page' => '#site', 'banner' => '#pub', 'title' => '#enseigne', 'menu' => '#menu a',
    'intro' => '#ouverture', 'news' => '#direct', 'agenda' => '#agenda', 'archive' => '#archives', 'focus' => '#club'];
$art = static fn (string $file, string $class = 'art'): string =>
    '<img class="' . $class . '" src="assets/borne-02/' . $file . '.png" alt="">';
$menuDefaults = $theme['defaults']['menu'];
$menuOriginal = $appearance['menu']['background'] === $menuDefaults['background']
    && $appearance['menu']['text'] === $menuDefaults['text'];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?= $t('name') ?> — <?= $t('tagline') ?></title>
<link rel="stylesheet" type="text/css" href="<?= $escape($theme['stylesheet']) ?>">
<style type="text/css">
<?php foreach (PortalAppearance::ZONES as $key => [, , , $width, $height]): ?>
<?= $selectors[$key] ?> { <?= $format($key) ?>; }
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
<p class="skip"><a href="#contenu">Aller au contenu</a></p>
<div id="site" class="zone-page">
<?php if ($preview): ?><p class="preview"><strong>Aperçu du brouillon</strong> | <a href="oldweb-editor.php">Retour aux réglages</a></p><?php endif ?>
<div class="utility zone-banner" id="pub">
<p class="tag"><a href="#agenda"><?= $t('banner') ?></a></p>
<p class="utility-links"><a href="#agenda">Agenda</a> | <a href="#credits">Crédits</a>
<?php if ($hasGif): ?> | <a href="<?= $preview ? '?preview=1' : '?page=1' ?><?= $still ? '' : '&amp;still=1' ?>"><?= $still ? 'Afficher les GIF' : 'Masquer les GIF' ?></a><?php endif ?></p>
<div class="clear"></div>
<?= $image('banner') ?>
</div>
<div class="mast" id="haut">
<h1 id="enseigne" class="zone-title site-name<?= mb_strlen($content['name'], 'UTF-8') > 13 ? ' wide' : '' ?>"><?= $t('name') ?></h1>
<ul id="menu" class="zone-menu<?= $menuOriginal ? ' menu-original' : '' ?>">
<li><a class="red" href="#ouverture">Arcade</a></li>
<li><a class="green" href="#journal">Journal</a></li>
<li><a class="blue" href="#dossiers">Dossiers</a></li>
<li><a class="gold" href="#club">Le club</a></li>
</ul>
<div class="clear"></div>
</div>
<div class="frame">
<div class="rail">
<div class="panel" id="journal">
<h2>Journal du club</h2>
<p class="status"><?= $t('status') ?></p>
<ul>
<li><a href="#ouverture"><?= $t('introTitle') ?></a></li>
<li><a href="#direct"><?= $t('newsTitle') ?></a></li>
<li><a href="#archives"><?= $t('archiveTitle') ?></a></li>
</ul>
</div>
<div class="panel zone-focus" id="club">
<h2><?= $t('focusTitle') ?></h2>
<?= $image('focus') ?: $art('commandes') ?>
<p><?= $t('focusText') ?></p>
<p><a href="#agenda">Voir la prochaine soirée</a></p>
</div>
</div>
<div class="main">
<a name="contenu" id="contenu" tabindex="-1"></a>
<h2 class="bar">À la une</h2>
<div class="feature">
<div class="spot zone-intro" id="ouverture">
<?php $introImage = $image('intro'); ?>
<?= $introImage ?: $art('orbite', 'intro-art') ?>
<p class="strap"><?= $t('frequency') ?></p>
<h3><?= $t('introTitle') ?></h3>
<p><?= $t('introText') ?></p>
<p><a href="#agenda">Voir le programme</a></p>
<div class="clear"></div>
</div>
<div class="event zone-agenda" id="agenda">
<h3>Soirée arcade</h3>
<?= $image('agenda') ?: $art('bonus') ?>
<p><strong><?= $t('agendaFirst') ?></strong></p>
<p><?= $t('agendaSecond') ?></p>
</div>
<div class="clear"></div>
</div>
<h2 class="bar picks-bar" id="dossiers">À l’affiche</h2>
<ul class="cards">
<li class="tile one">
<a href="#ouverture"><?= $art('orbitale-vignette') ?><strong><?= $t('introTitle') ?></strong></a>
<p>Le défi du mois</p>
</li>
<li class="tile zone-news two" id="direct">
<?= $image('news') ?: $art('balade') ?>
<h3><?= $t('newsTitle') ?></h3><p><?= $t('newsText') ?></p>
</li>
<li class="tile zone-archive three" id="archives">
<?= $image('archive') ?: $art('donjon') ?>
<h3><?= $t('archiveTitle') ?></h3><p><?= $t('archiveText') ?></p>
</li>
<li class="tile four">
<a href="#club"><?= $art('club') ?><strong><?= $t('focusTitle') ?></strong></a>
<p><?= $t('contact') ?></p>
</li>
</ul>
<div class="clear"></div>
</div>
<div class="clear"></div>
</div>
<div class="footer" id="credits">
<p><strong class="site-name"><?= $t('name') ?></strong> · <span class="site-tagline"><?= $t('tagline') ?></span> | <a href="#haut">Haut de page</a></p>
<p><a href="assets/borne-02/licences/illustrations.txt">Crédits des illustrations (CC0)</a></p>
</div>
</div>
</body>
</html>
