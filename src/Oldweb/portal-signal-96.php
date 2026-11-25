<?php
declare(strict_types=1);
require __DIR__ . '/portal-view.php';
$defaults = $theme['defaults'];
$zone = static function (string $key) use ($appearance, $defaults, $fontFamily, $escape): string {
    $a = $appearance[$key]; $d = $defaults[$key]; $s = [];
    if ($a['background'] !== $d['background']) $s[] = 'background-color:' . $a['background'];
    if ($a['text'] !== $d['text']) $s[] = 'color:' . $a['text'];
    if ($a['border'] !== 'none') $s[] = 'border:2px ' . $a['border'] . ' ' . $a['text'];
    $font = $fontFamily($key);
    if ($font !== '') $s[] = 'font-family:' . $font;
    return $s ? ' style="' . $escape(implode(';', $s)) . '"' : '';
};
$ink = static fn (string $key): string => $appearance[$key]['text'] !== $defaults[$key]['text'] ? ' style="color:' . $appearance[$key]['text'] . '"' : '';
$bg = $backgroundMode === 'theme' ? 'assets/signal-96/fond.gif' : $backgroundUrl;
$illustration = static function (string $key, int $max) use ($image, $appearance, $storage): string {
    $html = $image($key);
    if ($html === '') return '';
    $asset = $storage?->image($appearance[$key]['image'], $appearance);
    $size = $asset ? @getimagesize($asset['path']) : false;
    if ($size) {
        $scale = min(1, $max / $size[0], \Dendrila\Cms\Oldweb\PortalAppearance::ZONES[$key][4] / $size[1]);
        $html = str_replace('<img ', '<img width="' . max(1, (int) floor($size[0] * $scale)) . '" height="' . max(1, (int) floor($size[1] * $scale)) . '" ', $html);
    }
    return $html;
};
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<html lang="<?= $language ?>"><head><title><?= $t('name') ?> - <?= $t('tagline') ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="<?= $escape($theme['stylesheet']) ?>">
<style type="text/css">body { color:<?= $appearance['page']['text'] ?>;background-color:<?= $appearance['page']['background'] ?>;background-repeat:repeat;<?= $backgroundCss ?> }<?php foreach (['page' => 'td', 'title' => 'h1, .tagline', 'menu' => '.menu a'] as $key => $selector) {
    if (($font = $fontFamily($key)) !== '') echo "\n" . $selector . ' { font-family:' . $font . '; }';
} ?></style></head>
<body bgcolor="<?= $appearance['page']['background'] ?>"<?php if ($bg !== null): ?> background="<?= $escape($bg) ?>"<?php endif ?> text="<?= $appearance['page']['text'] ?>" link="#000099" vlink="#663399"<?= $zone('page') ?>>
<a name="haut"></a>
<center><?php if ($preview): ?><p><b><?= $tr('Aperçu du brouillon') ?></b> | <a href="oldweb-editor.php"><?= $tr('Retour aux réglages') ?></a></p><?php endif ?>
<?php if ($hasGif): ?><p><a href="<?= $preview ? '?preview=1' : '?page=1' ?><?= $still ? '' : '&amp;still=1' ?>"><?= $tr($still ? 'Afficher les GIF' : 'Masquer les GIF') ?></a></p><?php endif ?>
<table width="760" border="0" cellspacing="0" cellpadding="8">
<tr><td width="150" align="left" valign="top"<?= $zone('banner') ?>><font size="2"><b><?= $t('banner') ?></b><br>Metz &middot; France</font></td><td width="460" align="center"<?= $zone('title') ?>><font color="#89362f" size="2">&#8226; &nbsp; WWW &nbsp; &#8226;</font><h1 class="site-name"<?= $ink('title') ?>><?php if ($content['name'] === 'Signal 96' && $ink('title') === ''): ?>Signal <font color="#a24b35">96</font><?php else: ?><?= $t('name') ?><?php endif ?></h1><div class="tagline site-tagline"><?= $t('tagline') ?></div></td><td width="150" align="right" valign="top"><font size="2"><?= $t('frequency') ?><br><?= $tr('Novembre') ?> <?= $t('year') ?></font></td></tr>
<?php if ($appearance['banner']['image'] !== null): ?><tr><td colspan="3" align="center"><?= $illustration('banner', 490) ?></td></tr><?php endif ?>
</table>
<table width="740" border="0" cellspacing="0" cellpadding="4"><tr><td colspan="6"><hr size="2"></td></tr><tr class="menu"<?= $zone('menu') ?>><td width="120" align="center" valign="top"><a href="#bienvenue"<?= $ink('menu') ?>><img src="assets/signal-96/accueil.gif" width="64" height="64" border="0" alt=""><br><?= $tr('Bienvenue') ?></a></td>
<td width="120" align="center" valign="top"><a href="#exploration"<?= $ink('menu') ?>><img src="assets/signal-96/explorer.gif" width="64" height="64" border="0" alt=""><br>Exploration</a></td>
<td width="120" align="center" valign="top"><a href="#agenda"<?= $ink('menu') ?>><img src="assets/signal-96/agenda.gif" width="64" height="64" border="0" alt=""><br><?= $tr('Agenda') ?></a></td>
<td width="120" align="center" valign="top"><a href="#dossiers"<?= $ink('menu') ?>><img src="assets/signal-96/archives.gif" width="64" height="64" border="0" alt=""><br><?= $tr('Dossiers') ?></a></td>
<td width="120" align="center" valign="top"><a href="#club"<?= $ink('menu') ?>><img src="assets/signal-96/club.gif" width="64" height="64" border="0" alt=""><br><?= $tr('Le club') ?></a></td>
<td width="120" align="center" valign="top"><a href="#courrier"<?= $ink('menu') ?>><img src="assets/signal-96/courrier.gif" width="64" height="64" border="0" alt=""><br><?= $tr('Courrier') ?></a></td></tr><tr><td colspan="6"><hr size="2"></td></tr></table>
<table width="760" border="0" cellspacing="0" cellpadding="12">
<tr><td width="110" align="center" valign="top"><img src="assets/signal-96/fusee.gif" width="80" height="80" border="0" alt=""><p class="small"><i><?= $tr('Vers de nouveaux horizons') ?></i></p><br><img src="assets/signal-96/disquette.gif" width="80" height="80" border="0" alt=""></td>
<td width="492" valign="top"<?= $zone('intro') ?>><a name="bienvenue"></a><h2<?= $ink('intro') ?>><?= $t('introTitle') ?></h2><?= $illustration('intro', 420) ?>
<?php foreach (explode("\n\n", $content['introText']) as $paragraph): ?><p><?= $escape($paragraph) ?></p><?php endforeach ?>
<p><i><?= $tr('Bonne exploration, et à bientôt sur le réseau !') ?></i></p>
</td><td width="110" align="center" valign="middle"><img src="assets/signal-96/lunette.gif" width="80" height="80" border="0" alt=""><p class="small"><i><?= $tr('Le ciel de novembre') ?></i></p><br><img src="assets/signal-96/satellite.gif" width="80" height="80" border="0" alt=""></td></tr>
</table>
<table width="680" cellspacing="0" cellpadding="9" border="0">
<tr><td colspan="2"><hr><center><font size="4" color="#273d76"><b><?= $tr('Quoi de neuf au club ?') ?></b></font></center></td></tr>
<tr><td width="340" valign="top"><div<?= $zone('news') ?>><a name="exploration"></a><h3<?= $ink('news') ?>><?= $t('newsTitle') ?></h3><?= $illustration('news', 98) ?><p><?= $t('newsText') ?></p></div><div<?= $zone('agenda') ?>><a name="agenda"></a><h3<?= $ink('agenda') ?>><?= $tr('Agenda') ?></h3><?= $illustration('agenda', 310) ?><p><?= $t('agendaFirst') ?><br><?= $t('agendaSecond') ?></p></div></td>
<td width="340" valign="top"><div<?= $zone('archive') ?>><a name="dossiers"></a><h3<?= $ink('archive') ?>><?= $t('archiveTitle') ?></h3><?= $illustration('archive', 98) ?><p><?= $t('archiveText') ?></p></div><div<?= $zone('focus') ?>><a name="club"></a><h3<?= $ink('focus') ?>><?= $t('focusTitle') ?></h3><?= $illustration('focus', 149) ?><p><?= $t('focusText') ?></p></div></td></tr>
<tr><td colspan="2"><a name="courrier"></a><h3><?= $tr('Courrier') ?></h3><p><?= $t('contact') ?></p><hr></td></tr>
</table>
<table width="740" cellpadding="5" cellspacing="0" border="0"><tr><td width="240" valign="bottom"><font size="2"><font class="site-name"><?= $t('name') ?></font> &middot; <?= $tr('Site de démonstration') ?><br><?= $t('status') ?></font></td><td width="260" align="center"><a href="#haut"><img src="assets/signal-96/haut.gif" width="64" height="64" border="0" alt=""><br><?= $tr('Retour en haut') ?></a></td><td width="240" align="right" valign="bottom"><font size="2"><?= $tr('Fond :') ?> <a href="https://www.toptal.com/designers/subtlepatterns/tiny-grid/">Tiny Grid</a>, Atle Mo<br><a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a> &middot; <?= $tr('adaptation GIF') ?></font></td></tr></table>
</center></body></html>
