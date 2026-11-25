<?php
declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Connexion à Dendrila Retro</title>
<style type="text/css">
body { color:#111; background:#e1e7ff; font:100%/1.5 Verdana,sans-serif; }
#page { margin:1em auto; padding:1em; max-width:35em; background:#fff; border:2px solid #2455e5; }
label { display:block; margin-top:.8em; }
input { font:inherit; max-width:95%; min-height:44px; }
a:focus,input:focus { outline:2px solid #9f3600; }
</style>
</head>
<body>
<div id="page">
<h1>Connexion à Dendrila Retro</h1>
<p><a href="index.php">Retour au site</a></p>
<?php if ($error !== ''): ?><p><strong><?= $escape($error) ?></strong></p><?php endif ?>
<form method="post" action="oldweb-editor.php">
<div>
<input type="hidden" name="retro_token" value="<?= $escape($csrf) ?>">
<label for="owner-password">Mot de passe</label>
<input id="owner-password" type="password" name="password" size="30" maxlength="72">
<p><input type="submit" name="action" value="Se connecter"></p>
</div>
</form>
</div>
</body>
</html>
