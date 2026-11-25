<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalLocale;
?>
<p class="setup-language"><?= $tr('Langue d’installation') ?>:
<?php foreach (PortalLocale::LANGUAGES as $code => $label): ?>
<?php if ($code === $language): ?>
<strong lang="<?= $code ?>"><?= $label ?></strong>
<?php else: ?>
<a href="oldweb-editor.php?lang=<?= $code ?>" lang="<?= $code ?>" hreflang="<?= $code ?>"><?= $label ?></a>
<?php endif ?>
<?php endforeach ?>
</p>
