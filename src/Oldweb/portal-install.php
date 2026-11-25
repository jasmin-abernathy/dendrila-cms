<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalThemes;

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$name = is_string($submitted['name'] ?? null) ? substr($submitted['name'], 0, 220) : 'CANAL 57';
$tagline = is_string($submitted['tagline'] ?? null) ? substr($submitted['tagline'], 0, 400) : 'Le portail des noctambules | Metz | Depuis 2000';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Installer Dendrila Retro</title>
<style type="text/css">
body { color:#111; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; max-width:44em; background:#fff; border:2px solid #2455e5; }
label { display:block; margin-top:.8em; }
input,select { font:inherit; max-width:95%; min-height:44px; }
a:focus,input:focus,select:focus { outline:2px solid #9f3600; }
.error { color:#8b1800; background:#fff4ea; border:1px solid #8b1800; padding:.7em; }
</style>
</head>
<body>
<div id="page">
<h1>Installer Dendrila Retro</h1>
<p>Choisis le nom affiché sur le portail. Tu pourras ensuite personnaliser les couleurs, les bordures et les illustrations depuis le back-office.</p>
<?php if ($error !== ''): ?><p class="error"><strong><?= $escape($error) ?></strong></p><?php endif ?>
<form method="post" action="oldweb-editor.php">
<div>
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="revision" value="0">
<?php if (isset($standaloneDirectory)): ?>
<label for="activation">Clé d’installation (fichier LIRE-MOI.txt)</label>
<input id="activation" type="password" name="activation" size="38" maxlength="32">
<label for="owner-password">Choisir ton mot de passe (14 caractères minimum)</label>
<input id="owner-password" type="password" name="password" size="38" maxlength="72">
<?php endif ?>
<label for="site-name">Nom du site</label>
<input id="site-name" type="text" name="site[name]" size="38" maxlength="55" value="<?= $escape($name) ?>">
<label for="site-tagline">Sous-titre (facultatif)</label>
<input id="site-tagline" type="text" name="site[tagline]" size="38" maxlength="100" value="<?= $escape($tagline) ?>">
<label for="theme">Thème</label>
<select id="theme" name="theme">
<?php foreach (PortalThemes::AVAILABLE as $id => $entry): ?>
<option value="<?= $escape($id) ?>"<?= ($submittedTheme ?? PortalThemes::DEFAULT) === $id ? ' selected="selected"' : '' ?>><?= $escape($entry['label']) ?></option>
<?php endforeach ?>
</select>
<p><input type="submit" name="action" value="Installer Dendrila Retro"></p>
</div>
</form>
</div>
</body>
</html>
