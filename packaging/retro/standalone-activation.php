<?php
declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Créer ta clé d’installation — Dendrila Retro</title>
<style type="text/css">
body { color:#111; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; max-width:44em; background:#fff; border:2px solid #2455e5; }
label { display:block; margin-top:.8em; }
input { font:inherit; max-width:95%; min-height:44px; }
a { display:block; line-height:2.75; color:#16246b; }
a:focus,input:focus { outline:2px solid #9f3600; }
.error { color:#8b1800; }
code { font-size:.85em; }

</style>
</head>
<body>
<div id="page">
<h1>Créer ta clé d’installation</h1>
<p><strong>Installation protégée.</strong> Choisis une clé unique et garde-la à portée de main. Tu en auras besoin à l’étape suivante.</p>
<?php if ($error !== ''): ?><p class="error"><strong><?= $escape($error) ?></strong></p><?php endif ?>
<form method="post" action="oldweb-editor.php">
<div>
<input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
<label for="activation">Clé d’installation (24 caractères minimum)</label>
<input id="activation" type="password" name="activation" size="38" maxlength="128">
<p><input type="submit" name="action" value="Télécharger le fichier de clé"></p>
</div>
</form>
<p>Téléverse le fichier dans le dossier privé de ton hébergement, hors de toutes les racines web. Conserve le nom indiqué par le téléchargement.</p>
<p><a href="oldweb-editor.php">J’ai téléversé le fichier, continuer</a></p>
</div>
</body>
</html>
