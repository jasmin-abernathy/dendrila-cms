<?php
declare(strict_types=1);

// Router for the local, loopback-only preview. No production data is read or written.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

use Dendrila\Cms\Design\DesignKitCatalog;
use Dendrila\Cms\Design\OldwebAppearance;
use Dendrila\Cms\Design\PresentationResolver;
use Dendrila\Cms\Front\PublicPageRenderer;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Optional local media collection. Assets retain their original filenames.
$gifDirectory = getenv('DENDRILA_OLDWEB_GIF_DIR');
$gifLabels = [
    'stars.gif' => 'Ciel étoilé',
    'backgroundclouds.gif' => 'Nuages',
    'bluewater.gif' => 'Reflets de piscine',
    'circuitboard.gif' => 'Circuit imprimé',
    'bgpinkmarble.gif' => 'Marbre rose',
    'bricks.gif' => 'Briques blanches',
];
$defaultFilenames = array_keys($gifLabels);
foreach ($gifDirectory ? (glob($gifDirectory . '/*.gif') ?: []) : [] as $candidate) {
    $filename = basename($candidate);
    if (preg_match('/^[a-zA-Z0-9_-]+\.gif$/D', $filename) === 1) {
        $gifLabels[$filename] ??= pathinfo($filename, PATHINFO_FILENAME);
    }
}
$gifMetadata = [];
$gifMedia = [];
$gifChoices = [];
foreach ($gifLabels as $filename => $label) {
    $file = $gifDirectory ? $gifDirectory . '/' . $filename : null;
    if ($file === null || !is_file($file) || !is_readable($file)) {
        continue;
    }
    $image = @getimagesize($file);
    if (($image['mime'] ?? null) !== 'image/gif') {
        continue;
    }
    $url = '/media/oldweb/' . $filename;
    if ($path === $url) {
        header('Content-Type: image/gif');
        header('Cache-Control: private, max-age=3600');
        readfile($file);
        return;
    }
    $id = pathinfo($filename, PATHINFO_FILENAME);
    $gifMedia[$id] = $url;
    $gifMetadata[$id] = ['mime' => $image['mime'], 'width' => $image[0], 'height' => $image[1]];
    $gifChoices[$id] = $label;
}

if (in_array($path, ['/media/reference.svg', '/media/ripple-retro.svg'], true)) {
    header('Content-Type: image/svg+xml');
    readfile($root . '/examples/reference-media/' . basename($path));
    return;
}
if (in_array($path, ['/assets/oldweb/themes/retro.css', '/assets/oldweb-ripple.css', '/assets/oldweb-ripple.js'], true)) {
    return false;
}
if (!in_array($path, ['/', '/apparence', '/apercu'], true)) {
    http_response_code(404);
    return;
}

session_name('dendrila_oldweb_demo');
session_start(['use_strict_mode' => 1, 'cookie_httponly' => 1, 'cookie_samesite' => 'Strict']);
header('Cache-Control: no-store');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$defaults = ['style' => 'oldweb-personal', 'palette' => 'default', 'media_id' => '', 'speed' => 1.0, 'intensity' => 0.75];
$defaultGifs = array_intersect(array_map(
    static fn (string $filename): string => pathinfo($filename, PATHINFO_FILENAME),
    $defaultFilenames,
), array_keys($gifChoices));
$defaults['gif_ids'] = array_values($defaultGifs);
$state = $_SESSION['appearance'] ?? ['revision' => 0, 'published' => $defaults, 'previous' => null];
$catalog = new DesignKitCatalog($root . '/public/assets/oldweb/catalog.json');
$appearance = new OldwebAppearance(new PresentationResolver($root . '/design-styles', $catalog));
$composition = ['schema_version' => 1, 'functional_kit' => 'association', 'design_style' => $defaults['style']];
$media = ['ripple-demo' => '/media/ripple-retro.svg', 'hero' => '/media/reference.svg'] + $gifMedia;

$settings = $state['published'];
$error = '';
$message = '';
if ($path === '/apparence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
            http_response_code(403);
            throw new InvalidArgumentException('Session expirée. Recharge la page.');
        }
        if (($_POST['revision'] ?? null) !== (string) $state['revision']) {
            http_response_code(409);
            throw new InvalidArgumentException('L’apparence a changé dans un autre onglet. Vérifie les réglages avant de réessayer.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'restore' && $state['previous'] !== null) {
            $settings = $state['previous'];
        } elseif (in_array($action, ['preview', 'publish'], true)) {
            foreach ($defaults as $name => $default) {
                if ($name === 'gif_ids') {
                    continue;
                }
                $value = $_POST[$name] ?? null;
                if (!is_string($value) || (is_float($default) && !is_numeric($value))) {
                    throw new InvalidArgumentException('Vérifie tous les réglages.');
                }
                $settings[$name] = is_float($default) ? (float) $value : $value;
            }
            $mode = $_POST['background_mode'] ?? null;
            if (!in_array($mode, ['plain', 'gif', 'ripple'], true)) {
                throw new InvalidArgumentException('Choisis un type de fond.');
            }
            $settings['gif_ids'] = $mode === 'gif' ? ($_POST['gif_ids'] ?? null) : [];
            $settings['media_id'] = $mode === 'ripple' ? $settings['media_id'] : '';
        } else {
            throw new InvalidArgumentException('Action indisponible.');
        }
        $appearance->resolve($composition, $settings, $media, $gifMetadata);
        if ($action === 'preview') {
            $_SESSION['preview'] = $settings;
            $message = 'Aperçu prêt. La version publiée reste accessible depuis le site de démonstration.';
        } else {
            $_SESSION['appearance'] = [
                'revision' => $state['revision'] + 1,
                'published' => $settings,
                'previous' => $state['published'],
            ];
            unset($_SESSION['preview']);
            header('Location: /apparence', true, 303);
            return;
        }
    } catch (InvalidArgumentException $exception) {
        if (http_response_code() < 400) {
            http_response_code(422);
        }
        $error = $exception->getMessage();
    }
}

if ($path !== '/apparence') {
    $labels = OldwebAppearance::THEMES;
    $navigation = [['label' => 'Version publiée', 'url' => '/']];
    foreach ($labels as $id => $label) {
        $navigation[] = ['label' => $label, 'url' => '/?theme=' . $id];
    }
    $navigation[] = ['label' => 'Ripple', 'url' => '/?theme=oldweb-personal&ripple=1'];
    $navigation[] = ['label' => 'Personnaliser', 'url' => '/apparence'];
    if ($path === '/apercu') {
        $settings = $_SESSION['preview'] ?? $settings;
    } elseif (is_string($_GET['theme'] ?? null) && isset($labels[$_GET['theme']])) {
        $rippleDemo = ($_GET['ripple'] ?? '') === '1';
        $settings = array_replace($defaults, [
            'style' => $_GET['theme'],
            'gif_ids' => $rippleDemo ? [] : $defaults['gif_ids'],
            'media_id' => $rippleDemo ? 'ripple-demo' : '',
        ]);
    }
    $presentation = $appearance->resolve($composition, $settings, $media, $gifMetadata);
    echo (new PublicPageRenderer())->render(
        ['name' => 'Le Web des copains', 'tagline' => 'Carnet, liens et images', 'language' => 'fr'],
        ['title' => 'Bienvenue sur mon coin du Web', 'slug' => 'accueil', 'blocks' => [
            ['type' => 'text', 'text' => 'Une page faite à la main, avec un carnet de bord et des liens pour poursuivre la visite.'],
            ['type' => 'cards', 'heading' => 'À découvrir', 'items' => [
                ['title' => 'Le carnet', 'text' => 'Une bannière illustrée et une colonne pour prendre le temps de lire.', 'url' => '/?theme=oldweb-blog'],
                ['title' => 'Le portail', 'text' => 'Des rubriques à parcourir, regroupées en encadrés.', 'url' => '/?theme=oldweb-portal'],
                ['title' => 'Le fond Ripple', 'text' => 'Un fond local qui ondule. Tu peux le mettre en pause.', 'url' => '/?theme=oldweb-personal&ripple=1'],
            ]],
            ['type' => 'image', 'media_id' => 'hero', 'alt' => 'Illustration abstraite aux formes végétales'],
            ['type' => 'faq', 'heading' => 'Questions fréquentes', 'items' => [
                ['question' => 'Puis-je désactiver les mouvements ?', 'answer' => 'Oui, avec le bouton de pause ou la préférence système de réduction des animations.'],
            ]],
        ]],
        $presentation,
        '/assets/oldweb/themes/retro.css',
        $media,
        $navigation,
    );
    return;
}

$selectedGifs = $settings['gif_ids'] ?? [];
$backgroundMode = $selectedGifs !== [] ? 'gif' : ($settings['media_id'] !== '' ? 'ripple' : 'plain');
if ($error !== '' && in_array($_POST['background_mode'] ?? null, ['plain', 'gif', 'ripple'], true)) {
    $backgroundMode = $_POST['background_mode'];
}
$selectedGifs = is_array($selectedGifs) ? $selectedGifs : [];
function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function options(array $labels, string $selected): void
{
    foreach ($labels as $value => $label) {
        echo '<option value="' . esc($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
}
?>
<!doctype html>
<html lang="fr">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Personnaliser Oldweb</title>
<style>
body { color: #25212a; background: #f8f4fa; max-width: 50rem; margin: 2rem auto; padding: 0 1rem; font: 1rem/1.5 system-ui, sans-serif; }
label { display: block; margin-top: 1rem; font-weight: bold; }
select, input, button { font: inherit; min-height: 44px; max-width: 100%; }
:focus-visible { outline: 3px solid #603879; outline-offset: 3px; }
.actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.4rem; }
.gif-choice { display: block; min-height: 44px; margin: 0; font-weight: normal; }
.gif-choice input { min-height: 0; }
iframe { width: 100%; height: 34rem; border: 1px solid #65526e; box-sizing: border-box; }
</style>
<main>
<a href="/">Voir la version publiée</a>
<h1>Personnaliser Oldweb</h1>
<p>Les changements sont enregistrés dans cette session de démonstration.</p>
<?php if ($error !== ''): ?><p role="alert"><?= esc($error) ?></p><?php endif ?>
<?php if ($message !== ''): ?><p role="status"><?= esc($message) ?></p><?php endif ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
<input type="hidden" name="revision" value="<?= $state['revision'] ?>">
<label for="style">Thème</label>
<select id="style" name="style"><?php options(OldwebAppearance::THEMES, $settings['style']); ?></select>
<label for="palette">Couleur des liens</label>
<select id="palette" name="palette"><?php options(array_map(static fn (array $palette): string => $palette[0], OldwebAppearance::PALETTES), $settings['palette']); ?></select>
<fieldset>
<legend>Fond de page</legend>
<label for="background-mode">Type de fond</label>
<select id="background-mode" name="background_mode"><?php options(['plain' => 'Fond du thème', 'gif' => 'Mosaïque GIF', 'ripple' => 'Ripple'], $backgroundMode); ?></select>
<fieldset>
<legend>GIF de la mosaïque</legend>
<p>Choisis de 1 à 10 GIF. Un seul GIF sera répété sur toutes les lignes.</p>
<?php if ($gifChoices === []): ?><p>Aucun GIF local disponible dans cette instance.</p><?php endif ?>
<?php foreach ($gifChoices as $gifId => $gifLabel): ?>
<label class="gif-choice"><input type="checkbox" name="gif_ids[]" value="<?= esc($gifId) ?>"<?= in_array($gifId, $selectedGifs, true) ? ' checked' : '' ?>> <?= esc($gifLabel) ?></label>
<?php endforeach ?>
</fieldset>
<label for="media">Image de fond Ripple</label>
<select id="media" name="media_id"><?php options(['' => 'Désactivé', 'ripple-demo' => 'Ciel rétro'], $settings['media_id']); ?></select>
<label for="speed">Vitesse (0,25 à 2)</label>
<input id="speed" type="number" name="speed" min="0.25" max="2" step="0.01" value="<?= esc((string) $settings['speed']) ?>" required>
<label for="intensity">Intensité (0 à 1)</label>
<input id="intensity" type="number" name="intensity" min="0" max="1" step="0.01" value="<?= esc((string) $settings['intensity']) ?>" required>
</fieldset>
<div class="actions">
<button name="action" value="preview">Prévisualiser</button>
<button name="action" value="publish">Publier dans la démo</button>
<?php if ($state['previous'] !== null): ?><button name="action" value="restore" formnovalidate>Rétablir la version précédente</button><?php endif ?>
</div>
</form>
<?php if (isset($_SESSION['preview'])): ?>
<h2>Aperçu</h2>
<iframe src="/apercu" title="Aperçu de l’apparence Oldweb"></iframe>
<?php endif ?>
</main>
