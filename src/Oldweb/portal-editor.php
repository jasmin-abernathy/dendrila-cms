<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalAppearance;
use Dendrila\Cms\Oldweb\PortalLocale;
use Dendrila\Cms\Oldweb\PortalThemes;

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="<?= $language ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?= $tr('Apparence du portail Oldweb') ?></title>
<style type="text/css">
body { color:#111; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; max-width:56em; background:#fff; border:2px solid #2455e5; }
fieldset { margin:1em 0; border:1px solid #232a55; }
legend { font-weight:bold; color:#101048; }
label { display:inline; margin:.2em .7em .2em 0; }
label.image { display:block; }
button,input,select { font:inherit; padding:.25em; }
a:focus,button:focus,input:focus,select:focus { outline:2px solid #9f3600; }
.error { color:#8b1800; background:#fff4ea; border:1px solid #8b1800; padding:.7em; }
</style>
</head>
<body>
<div id="page">
<p><a href="oldweb-portal.php"><?= $tr('Page publiée') ?></a> | <a href="?preview=1"><?= $tr('Voir le brouillon') ?></a></p>
<h1><?= $tr('Apparence du portail Oldweb') ?></h1>
<?php if (isset($standaloneLogout)): ?>
<form method="post" action="oldweb-editor.php">
<div><input type="hidden" name="retro_token" value="<?= $escape($standaloneLogout) ?>">
<input type="hidden" name="action" value="logout">
<input type="submit" value="<?= $tr('Se déconnecter') ?>"></div>
</form>
<?php endif ?>
<?php if ($message !== ''): ?><p><strong><?= $tr($message) ?></strong></p><?php endif ?>
<?php if ($error !== ''): ?><p class="error"><strong><?= $tr($error) ?></strong></p><?php endif ?>
<form method="post" action="oldweb-editor.php">
<fieldset>
<legend><?= $tr('Langue') ?></legend>
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="revision" value="<?= $state['revision'] ?>">
<input type="hidden" name="action" value="language">
<label for="retro-language"><?= $tr('Langue') ?></label>
<select id="retro-language" name="language">
<?php foreach (PortalLocale::LANGUAGES as $code => $label): ?>
<option value="<?= $code ?>" lang="<?= $code ?>"<?= $language === $code ? ' selected="selected"' : '' ?>><?= $label ?></option>
<?php endforeach ?>
</select>
<input type="submit" value="<?= $tr('Appliquer la langue') ?>">
<p><?= $tr('Cette langue s’applique au back-office et aux textes de démonstration du site.') ?></p>
</fieldset>
</form>
<p><?= $tr('Utilise des couleurs') ?> <code>#RRGGBB</code>. <?= $tr('Les polices sont choisies parmi celles disponibles sur l’appareil du visiteur. Les fichiers GIF, PNG et JPEG sont limités à 1 Mio et 1 600 px par côté. Renseigne un texte de remplacement si l’image donne une information.') ?></p>
<form method="post" action="oldweb-editor.php" enctype="multipart/form-data">
<div>
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="revision" value="<?= $state['revision'] ?>">
<label for="retro-theme"><?= $tr('Thème du portail') ?></label>
<select id="retro-theme" name="theme">
<?php foreach (PortalThemes::AVAILABLE as $id => $entry): ?>
<option value="<?= $escape($id) ?>"<?= ($submittedTheme ?? $state['themes']['draft']) === $id ? ' selected="selected"' : '' ?>><?= $escape($entry['label']) ?></option>
<?php endforeach ?>
</select>
<p><label><input name="theme_palette" type="checkbox" value="1"<?= ($submittedPalette ?? false) ? ' checked="checked"' : '' ?>> <?= $tr('Appliquer les couleurs et les bordures d’origine du thème sélectionné') ?></label></p>
</div>
<?php foreach (PortalAppearance::ZONES as $key => [$label, , , $width]): ?>
<fieldset>
<legend><?= $tr($label) ?></legend>
<?php
    $zone = $state['draft'][$key];
    $font = $submitted[$key]['font'] ?? $zone['font'] ?? 'theme';
    if (!is_string($font) || !isset(PortalAppearance::FONTS[$font])) {
        $font = 'theme';
    }
    foreach (['background', 'text', 'border', 'alt'] as $field) {
        $value = $submitted[$key][$field] ?? null;
        if (is_string($value) && strlen($value) <= 800) {
            $zone[$field] = $value;
        }
    }
?>
<label for="<?= $key ?>-bg"><?= $tr('Fond') ?>
<input id="<?= $key ?>-bg" name="zones[<?= $key ?>][background]" type="text" size="8" maxlength="7" value="<?= $escape($zone['background']) ?>"></label>
<label for="<?= $key ?>-text"><?= $tr('Texte') ?>
<input id="<?= $key ?>-text" name="zones[<?= $key ?>][text]" type="text" size="8" maxlength="7" value="<?= $escape($zone['text']) ?>"></label>
<label for="<?= $key ?>-border"><?= $tr('Bordure') ?>
<select id="<?= $key ?>-border" name="zones[<?= $key ?>][border]">
<?php foreach (PortalAppearance::BORDERS as $border => $borderLabel): ?>
<option value="<?= $border ?>"<?= $zone['border'] === $border ? ' selected="selected"' : '' ?>><?= $tr($borderLabel) ?></option>
<?php endforeach ?>
</select></label>
<label for="<?= $key ?>-font"><?= $tr($key === 'page' ? 'Police générale' : 'Police') ?>
<select id="<?= $key ?>-font" name="zones[<?= $key ?>][font]">
<?php foreach (PortalAppearance::FONTS as $id => [$fontLabel]): ?>
<option value="<?= $id ?>"<?= $font === $id ? ' selected="selected"' : '' ?>><?= $tr($fontLabel) ?></option>
<?php endforeach ?>
</select></label>
<?php if ($key === 'page'): ?>
<p><label for="page-mode"><?= $tr('Type de fond') ?></label>
<select id="page-mode" name="zones[page][mode]">
<?php foreach (['theme'=>'Fond du thème','color'=>'Couleur unie','image'=>'Image répétée'] as $mode => $modeLabel): ?>
<option value="<?= $mode ?>"<?= ($submitted['page']['mode'] ?? $zone['mode'] ?? 'theme') === $mode ? ' selected="selected"' : '' ?>><?= $tr($modeLabel) ?></option>
<?php endforeach ?></select></p>
<p><?= $tr('Le motif est répété horizontalement et verticalement à sa taille d’origine. La couleur reste disponible en mode uni.') ?></p>
<?php endif ?>
<?php if ($width > 0): ?>
<?php if ($zone['image'] !== null): ?>
<p><a href="?preview=1&amp;media=<?= $escape($zone['image']) ?>"><?= $tr('Voir l’image actuelle') ?></a>
<label><input name="zones[<?= $key ?>][remove]" type="checkbox" value="1"> <?= $tr($key === 'page' ? 'Retirer le fichier de fond' : 'Retirer l’image') ?></label></p>
<?php endif ?>
<label class="image" for="<?= $key ?>-image"><?= $tr($key === 'page' ? 'Image de fond' : 'Nouvelle image') ?>
<input id="<?= $key ?>-image" name="image_<?= $key ?>" type="file" accept="image/gif,image/png,image/jpeg"></label>
<?php if ($key !== 'page'): ?><label class="image" for="<?= $key ?>-alt"><?= $tr('Texte de remplacement') ?>
<input id="<?= $key ?>-alt" name="zones[<?= $key ?>][alt]" type="text" size="42" maxlength="200" value="<?= $escape($zone['alt']) ?>"></label><?php endif ?>
<?php endif ?>
</fieldset>
<?php endforeach ?>
<p><button type="submit" name="action" value="draft"><?= $tr('Enregistrer le brouillon') ?></button>
<button type="submit" name="action" value="publish"><?= $tr('Publier') ?></button>
<?php if ($state['previous'] !== null): ?><button type="submit" name="action" value="restore"><?= $tr('Rétablir la version précédente') ?></button><?php endif ?></p>
</form>
</div>
</body>
</html>
