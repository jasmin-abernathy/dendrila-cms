<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalAppearance;

require __DIR__ . '/portal-view.php';

$selectors = [
    'page' => 'body', 'banner' => '#pub', 'title' => '#enseigne', 'menu' => '#orbite a',
    'intro' => '#ecoute', 'news' => '#journal', 'agenda' => '#carte', 'archive' => '#dossiers', 'focus' => '#club',
];
$navigation = [
    'antenne' => ['ecoute', 'Salle d’écoute'],
    'planete' => ['carte', 'Carte du ciel'],
    'classeur' => ['dossiers', 'Dossiers'],
    'carnet' => ['journal', 'Journal du relais'],
    'cassette' => ['club', 'Le club'],
    'lettre' => ['courrier', 'Courrier'],
];
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
body { <?= $backgroundCss ?> }
<?php endif ?>
</style>
</head>
<body>
<p class="skip"><a href="#contenu">Aller au contenu</a></p>
<div id="site" class="zone-page">
<?php if ($preview): ?><p class="preview"><strong>Aperçu du brouillon</strong> | <a href="oldweb-editor.php">Retour aux réglages</a></p><?php endif ?>
<div id="pub" class="zone-banner">
<p><a href="#carte"><?= $t('banner') ?></a></p>
<?= $image('banner') ?>
</div>
<div id="orbite">
<div id="station">
<h1 id="enseigne" class="zone-title<?= mb_strlen($content['name'], 'UTF-8') > 15 ? ' long' : '' ?>"><?= $t('name') ?></h1>
<p class="tagline"><?= $t('tagline') ?></p>
<p class="frequence"><?= $t('frequency') ?></p>
</div>
<ul class="zone-menu">
<?php foreach ($navigation as $icon => [$target, $label]): ?>
<li class="<?= $icon ?>"><a href="#<?= $target ?>"><span></span><?= $escape($label) ?></a></li>
<?php endforeach ?>
</ul>
</div>
<p class="liens-texte">
<?php foreach ($navigation as $icon => [$target, $label]): ?>
<?= $icon === 'antenne' ? '' : ' | ' ?><a href="#<?= $target ?>"><?= $escape($label) ?></a>
<?php endforeach ?>
</p>
<p class="statut"><?= $t('status') ?></p>
<?php if ($hasGif): ?><p class="statut"><a href="<?= $preview ? '?preview=1' : '?page=1' ?><?= $still ? '' : '&amp;still=1' ?>"><?= $still ? 'Afficher les GIF' : 'Masquer les GIF' ?></a></p><?php endif ?>

<div class="dossiers">
<a name="contenu" id="contenu" tabindex="-1"></a>
<div id="ecoute" class="fiche zone-intro">
<h2><?= $t('introTitle') ?></h2>
<?= $image('intro') ?>
<p><?= $t('introText') ?></p>
</div>
<div id="carte" class="fiche zone-agenda">
<h2>Carte du ciel</h2>
<?= $image('agenda') ?>
<ul><li><?= $t('agendaFirst') ?></li><li><?= $t('agendaSecond') ?></li></ul>
</div>
<div id="dossiers" class="fiche zone-archive">
<h2><?= $t('archiveTitle') ?></h2>
<?= $image('archive') ?>
<p><?= $t('archiveText') ?></p>
</div>
<div id="journal" class="fiche zone-news">
<h2><?= $t('newsTitle') ?></h2>
<?= $image('news') ?>
<p><?= $t('newsText') ?></p>
</div>
<div id="club" class="fiche zone-focus">
<h2><?= $t('focusTitle') ?></h2>
<?= $image('focus') ?>
<p><?= $t('focusText') ?></p>
<h3 id="courrier">Courrier</h3>
<p><?= $t('contact') ?></p>
</div>
</div>
<hr>
<p class="pied"><a href="#pub">Retour à l’enseigne</a> | <?= $t('name') ?>, <?= $t('year') ?> · Illustrations d’origine sous CC0</p>
</div>
</body>
</html>
