<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalThemes;

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$standalone = isset($standaloneDirectory);
$name = is_string($submitted['name'] ?? null) ? substr($submitted['name'], 0, 220) : '';
$tagline = is_string($submitted['tagline'] ?? null) ? substr($submitted['tagline'], 0, 400) : '';
$chosen = $submittedTheme ?? PortalThemes::DEFAULT;
if ($standalone) {
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; script-src 'self'; frame-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installer Dendrila Retro</title>
<style>
body { margin:0; color:#141932; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; width:calc(100% - 2.5em); max-width:58em; box-sizing:border-box; background:#fff; border:2px solid #2455e5; }
h1 { margin-top:0; }
label { display:block; font-weight:bold; margin-top:.8em; }
input,select { font:inherit; max-width:100%; min-height:44px; box-sizing:border-box; }
a:focus,input:focus,select:focus,.preview-scroll:focus { outline:2px solid #9f3600; }
.error,.notice { padding:.7em; border:1px solid #8b1800; background:#fff4ea; color:#632000; }
.preview-scroll { overflow:auto; max-width:100%; border:2px solid #253469; }
iframe { display:block; width:100%; min-width:844px; height:470px; border:0; }
.theme-links a { display:inline-block; margin:0 .6em .45em 0; }
.install { margin-top:1.3em; }
</style>
<?php if ($standalone): ?><script src="assets/installer-preview.js" defer></script><?php endif ?>
</head>
<body>
<main id="page">
<h1>Installer Dendrila Retro</h1>
<?php if ($standalone && !$requiresKey): ?>
<p class="notice">Tant que le site n’est pas installé, la première personne qui termine ce formulaire devient administratrice. Termine maintenant ou protège temporairement le site depuis ton hébergement.</p>
<?php elseif ($standalone && !$personal): ?>
<p>Le mode protégé est actif. Saisis la clé correspondant au fichier déposé dans ton dossier privé.</p>
<?php endif ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><strong><?= $escape($error) ?></strong></p><?php endif ?>
<form method="post" action="oldweb-editor.php">
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<input type="hidden" name="revision" value="0">
<label for="site-name">Nom du site</label>
<input id="site-name" type="text" name="site[name]" size="38" maxlength="55" required value="<?= $escape($name) ?>">
<label for="site-tagline">Sous-titre (facultatif)</label>
<input id="site-tagline" type="text" name="site[tagline]" size="38" maxlength="100" value="<?= $escape($tagline) ?>">
<label for="theme">Thème</label>
<select id="theme" name="theme">
<?php foreach (PortalThemes::AVAILABLE as $id => $entry): ?>
<option value="<?= $escape($id) ?>"<?= $chosen === $id ? ' selected' : '' ?>><?= $escape($entry['label']) ?></option>
<?php endforeach ?>
</select>
<?php if ($standalone): ?>
<h2>Aperçu interactif</h2>
<p class="theme-links">
<?php foreach (PortalThemes::AVAILABLE as $id => $entry): ?>
<a href="preview.php?theme=<?= $escape($id) ?>" target="retro-theme-preview" data-preview-theme="<?= $escape($id) ?>">Voir <?= $escape($entry['label']) ?></a>
<?php endforeach ?>
</p>
<noscript><p>Les liens ouvrent l’aperçu sans effacer le formulaire. Choisis ensuite le même thème dans la liste pour l’installer.</p></noscript>
<div class="preview-scroll" tabindex="0" aria-label="Aperçu à largeur fixe, défilable horizontalement">
<iframe name="retro-theme-preview" id="theme-preview" src="preview.php?theme=<?= $escape($chosen) ?>" title="Aperçu interactif du thème" sandbox="allow-same-origin"></iframe>
</div>
<p><a id="theme-full-size" href="preview.php?theme=<?= $escape($chosen) ?>" target="_blank" rel="noopener">Ouvrir cet aperçu en taille réelle</a></p>
<?php if ($requiresKey): ?>
<label for="activation"><?= $personal ? 'Clé d’installation (fichier LIRE-MOI.txt)' : 'Clé d’installation' ?></label>
<input id="activation" type="password" name="activation" size="38" maxlength="<?= $personal ? 32 : 128 ?>" autocomplete="off" required>
<?php endif ?>
<label for="owner-username">Identifiant administrateur</label>
<input id="owner-username" type="text" name="username" size="32" maxlength="32" autocomplete="username" autocapitalize="none" spellcheck="false" required value="<?= $escape($submittedUsername) ?>" aria-describedby="username-hint">
<p id="username-hint">De 3 à 32 caractères. Lettres sans accent, chiffres, points, tirets et tirets bas.</p>
<label for="owner-password">Mot de passe</label>
<input id="owner-password" type="password" name="password" size="38" minlength="14" maxlength="72" autocomplete="new-password" required aria-describedby="password-hint">
<p id="password-hint">Au moins 14 caractères, 72 octets maximum.</p>
<label for="password-confirmation">Confirmer le mot de passe</label>
<input id="password-confirmation" type="password" name="password_confirmation" size="38" minlength="14" maxlength="72" autocomplete="new-password" required>
<?php endif ?>
<p class="install"><input type="submit" name="action" value="Installer Dendrila Retro"></p>
</form>
</main>
</body>
</html>
