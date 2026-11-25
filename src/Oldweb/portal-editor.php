<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalAppearance;

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Apparence du portail Oldweb</title>
<style type="text/css">
body { color:#111; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; max-width:56em; background:#fff; border:2px solid #2455e5; }
fieldset { margin:1em 0; border:1px solid #232a55; }
legend { font-weight:bold; color:#101048; }
label { display:inline; margin:.2em .7em .2em 0; }
label.image { display:block; }
input,select { font:inherit; padding:.25em; }
a:focus,input:focus,select:focus { outline:2px solid #9f3600; }
.error { color:#8b1800; background:#fff4ea; border:1px solid #8b1800; padding:.7em; }
</style>
</head>
<body>
<div id="page">
<p><a href="oldweb-portal.php">Page publiée</a> | <a href="?preview=1">Voir le brouillon</a></p>
<h1>Apparence du portail Oldweb</h1>
<?php if ($message !== ''): ?><p><strong><?= $escape($message) ?></strong></p><?php endif ?>
<?php if ($error !== ''): ?><p class="error"><strong><?= $escape($error) ?></strong></p><?php endif ?>
<p>Utilise des couleurs <code>#RRGGBB</code>. Les fichiers GIF, PNG et JPEG sont limités à 1 Mio et 1 600 px par côté. Renseigne un texte de remplacement si l’image donne une information.</p>
<form method="post" action="oldweb-editor.php" enctype="multipart/form-data">
<div>
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="revision" value="<?= $state['revision'] ?>">
</div>
<?php foreach (PortalAppearance::ZONES as $key => [$label, , , $width]): ?>
<fieldset>
<legend><?= $escape($label) ?></legend>
<?php
    $zone = $state['draft'][$key];
    foreach (['background', 'text', 'border', 'alt'] as $field) {
        $value = $submitted[$key][$field] ?? null;
        if (is_string($value) && strlen($value) <= 800) {
            $zone[$field] = $value;
        }
    }
?>
<label for="<?= $key ?>-bg">Fond
<input id="<?= $key ?>-bg" name="zones[<?= $key ?>][background]" type="text" size="8" maxlength="7" value="<?= $escape($zone['background']) ?>"></label>
<label for="<?= $key ?>-text">Texte
<input id="<?= $key ?>-text" name="zones[<?= $key ?>][text]" type="text" size="8" maxlength="7" value="<?= $escape($zone['text']) ?>"></label>
<label for="<?= $key ?>-border">Bordure
<select id="<?= $key ?>-border" name="zones[<?= $key ?>][border]">
<?php foreach (PortalAppearance::BORDERS as $border => $borderLabel): ?>
<option value="<?= $border ?>"<?= $zone['border'] === $border ? ' selected="selected"' : '' ?>><?= $escape($borderLabel) ?></option>
<?php endforeach ?>
</select></label>
<?php if ($width > 0): ?>
<?php if ($zone['image'] !== null): ?>
<p><a href="?preview=1&amp;media=<?= $escape($zone['image']) ?>">Voir l’image actuelle</a>
<label><input name="zones[<?= $key ?>][remove]" type="checkbox" value="1"> Retirer l’image</label></p>
<?php endif ?>
<label class="image" for="<?= $key ?>-image">Nouvelle image
<input id="<?= $key ?>-image" name="image_<?= $key ?>" type="file" accept="image/gif,image/png,image/jpeg"></label>
<label class="image" for="<?= $key ?>-alt">Texte de remplacement
<input id="<?= $key ?>-alt" name="zones[<?= $key ?>][alt]" type="text" size="42" maxlength="200" value="<?= $escape($zone['alt']) ?>"></label>
<?php endif ?>
</fieldset>
<?php endforeach ?>
<p><input type="submit" name="action" value="Enregistrer le brouillon">
<input type="submit" name="action" value="Publier">
<?php if ($state['previous'] !== null): ?><input type="submit" name="action" value="Rétablir la version précédente"><?php endif ?></p>
</form>
</div>
</body>
</html>
