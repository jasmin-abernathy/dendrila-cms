<?php
declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $language ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $tr('Connexion à Dendrila Retro') ?></title>
<style type="text/css">
body { color:#111; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; max-width:35em; background:#fff; border:2px solid #2455e5; }
label { display:block; margin-top:.8em; }
input { box-sizing:border-box; font:inherit; max-width:95%; min-height:44px; }
a:focus,input:focus { outline:2px solid #9f3600; }
</style>
</head>
<body>
<main id="page">
<h1><?= $tr('Connexion à Dendrila Retro') ?></h1>
<p><a href="index.php"><?= $tr('Retour au site') ?></a></p>
<?php if ($error !== ''): ?><p role="alert"><strong><?= $escape($error) ?></strong></p><?php endif ?>
<form method="post" action="oldweb-editor.php">
<div>
<input type="hidden" name="retro_token" value="<?= $escape($csrf) ?>">
<?php if ($ownerUsername !== null): ?>
<label for="owner-username"><?= $tr('Identifiant administrateur') ?></label>
<input id="owner-username" type="text" name="username" size="30" maxlength="32" autocomplete="username" autocapitalize="none" value="<?= $escape($submittedUsername) ?>" required>
<?php endif ?>
<label for="owner-password"><?= $tr('Mot de passe') ?></label>
<input id="owner-password" type="password" name="password" size="30" maxlength="72" autocomplete="current-password" required>
<p><input type="hidden" name="action" value="login">
<input type="submit" value="<?= $tr('Se connecter') ?>"></p>
</div>
</form>
</main>
</body>
</html>
