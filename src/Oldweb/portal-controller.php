<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalStorage;

/** @var bool $editor Set by the public entrypoint, never by a request parameter. */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

try {
    $directory = getenv('DENDRILA_OLDWEB_PORTAL_DIR');
    if ($directory === false || $directory === '') {
        http_response_code(404);
        return;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, $editor ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD'], true)) {
        header('Allow: ' . ($editor ? 'GET, HEAD, POST' : 'GET, HEAD'));
        http_response_code(405);
        return;
    }

    header('Cache-Control: ' . ($editor ? 'no-store' : 'no-cache'));
    if ($editor) {
        $https = in_array(strtolower((string) ($_SERVER['HTTPS'] ?? '')), ['on', '1'], true);
        $allowed = preg_split('/\s+/', trim(getenv('DENDRILA_OLDWEB_PORTAL_EDITORS') ?: ''), -1, PREG_SPLIT_NO_EMPTY);
        $user = $_SERVER['REMOTE_USER'] ?? '';
        if (!$https || !is_string($user) || $user === '' || !in_array($user, $allowed ?: [], true)) {
            http_response_code(403);
            return;
        }
    }

    $storage = new PortalStorage($directory, $_SERVER['DOCUMENT_ROOT'] ?? '');
    $state = $storage->state();
    $preview = $editor && $method !== 'POST' && ($_GET['preview'] ?? '') === '1';
    $appearance = $preview ? $state['draft'] : $state['published'];
    if (array_key_exists('media', $_GET)) {
        $media = $_GET['media'];
        $asset = is_string($media) && (!$editor || $preview) && $method !== 'POST'
            ? $storage->image($media, $appearance) : null;
        if ($asset === null) {
            http_response_code(404);
            return;
        }
        header('Content-Type: ' . $asset['mime']);
        if ($method === 'GET') {
            readfile($asset['path']);
        }
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    if ($method === 'HEAD') {
        return;
    }
    if (!$editor || $preview) {
        $content = json_decode((string) file_get_contents($root . '/examples/oldweb/portal-content.json'), true, 32, JSON_THROW_ON_ERROR);
        $custom = $directory . '/content.json';
        if (is_file($custom)) {
            $values = json_decode((string) file_get_contents($custom), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($values) || array_diff_key($values, $content)
                || count(array_filter($values, static fn (mixed $value): bool => is_string($value) && strlen($value) <= 1000)) !== count($values)) {
                throw new RuntimeException('Invalid Oldweb portal content.');
            }
            $content = array_replace($content, $values);
        }
        $still = ($_GET['still'] ?? '') === '1';
        require __DIR__ . '/portal-page.php';
        return;
    }

    session_name('DENDRILA_OLDWEB');
    if (!session_start(['use_strict_mode' => 1, 'cookie_httponly' => true, 'cookie_secure' => true, 'cookie_samesite' => 'Strict'])) {
        throw new RuntimeException('Cannot open editor session.');
    }
    $tokens = $_SESSION['oldweb_csrf'] ?? [];
    $tokenKey = hash('sha256', $directory . "\0" . $user);
    $csrf = $tokens[$tokenKey] ?? bin2hex(random_bytes(32));
    $_SESSION['oldweb_csrf'][$tokenKey] = $csrf;
    $error = '';
    $submitted = [];
    if ($method === 'POST') {
        $actions = ['Enregistrer le brouillon' => 'draft', 'Publier' => 'publish', 'Rétablir la version précédente' => 'restore'];
        try {
            if (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf'])) {
                http_response_code(403);
                echo 'La session du formulaire a expiré. Retourne à la page d’apparence et recharge-la.';
                return;
            }
            $revision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $action = $_POST['action'] ?? null;
            $fields = $_POST['zones'] ?? null;
            if ($revision === false || !is_string($action) || !isset($actions[$action]) || !is_array($fields)) {
                throw new InvalidArgumentException('Le formulaire est incomplet.');
            }
            $change = $actions[$action];
            $uploads = $change === 'restore' ? [] : PortalStorage::uploads($_FILES);
            if (!$storage->change($revision, $change, $fields, $uploads)) {
                $state = $storage->state();
                http_response_code(409);
                $error = 'Une autre modification a été enregistrée. Vérifie la version actuelle avant de réessayer.';
            } else {
                header('Location: oldweb-editor.php?result=' . $change, true, 303);
                return;
            }
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
            $submitted = is_array($fields ?? null) ? $fields : [];
        }
    }
    $messages = ['draft' => 'Brouillon enregistré.', 'publish' => 'Apparence publiée.', 'restore' => 'Version précédente rétablie.'];
    $result = $_GET['result'] ?? null;
    $message = is_string($result) ? ($messages[$result] ?? '') : '';
    require __DIR__ . '/portal-editor.php';
} catch (Throwable $error) {
    error_log('Oldweb portal: ' . $error->getMessage());
    http_response_code(503);
    echo 'Portail momentanément indisponible.';
}
