<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

use InvalidArgumentException;
use RuntimeException;

final readonly class Standalone
{
    public function __construct(private array $bundle) {}

    public function directory(string $documentRoot, string $home): string
    {
        $root = realpath($documentRoot);
        // cPanel may omit HOME when it runs PHP through FastCGI.
        if (is_string($root) && preg_match('~^(/home[0-9]*/[^/]+)/~D', $root, $parts) === 1) {
            $home = $parts[1];
        }
        $home = $home !== '' ? realpath($home) : false;
        if (!is_string($root) || !is_string($home) || $home === '/'
            || !str_starts_with($root, $home . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Dendrila Retro ne trouve pas d’espace privé sur cet hébergement. Vérifie que les fichiers sont déposés dans le dossier de ton domaine, depuis ton compte cPanel.');
        }
        $directory = $home . '/.dendrila-retro-' . $this->bundle['id'];
        if (is_link($directory) || (is_dir($directory) && (fileperms($directory) & 0077) !== 0)) {
            throw new RuntimeException('Dendrila Retro ne peut pas ouvrir son espace privé. Vérifie ses permissions dans cPanel.');
        }
        return $directory;
    }

    /** Return true only for an installed public page or an authenticated editor. */
    public function enter(string $standaloneDirectory, bool $editor): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!in_array($method, $editor ? ['GET', 'HEAD', 'POST'] : ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: ' . ($editor ? 'GET, HEAD, POST' : 'GET, HEAD'));
            return false;
        }
        $installed = is_file($standaloneDirectory . '/appearance.json');
        if (!$editor) {
            if (!$installed) {
                header('Cache-Control: no-store');
                header('Location: oldweb-editor.php', true, 302);
            }
            return $installed;
        }

        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=UTF-8');
        if (!in_array(strtolower((string) ($_SERVER['HTTPS'] ?? '')), ['on', '1'], true)) {
            http_response_code(403);
            echo 'Ouvre cette page avec une adresse qui commence par https://.';
            return false;
        }
        session_name('DENDRILA_RETRO_' . $this->bundle['id']);
        if (!session_start(['use_strict_mode' => 1, 'cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Strict'])) {
            throw new RuntimeException('Dendrila Retro ne peut pas ouvrir de session sur cet hébergement.');
        }

        $store = $installed ? new PortalStorage($standaloneDirectory, $_SERVER['DOCUMENT_ROOT']) : null;
        $hash = $store?->state()['owner_password_hash'] ?? '';
        if ($installed && (!is_string($hash) || $hash === '')) {
            throw new RuntimeException('Le compte de ce portail doit être restauré depuis une sauvegarde.');
        }
        $signedIn = $installed && ($_SESSION['retro_user'] ?? '') === hash('sha256', $hash);
        $csrf = $_SESSION['retro_token'] ??= bin2hex(random_bytes(32));
        $action = $_POST['action'] ?? '';
        if ($signedIn && ($method !== 'POST' || $action !== 'Se déconnecter')) {
            return true;
        }
        if (array_key_exists('media', $_GET) || array_key_exists('preview', $_GET)) {
            http_response_code(403);
            return false;
        }

        $error = '';
        $submitted = [];
        $personal = isset($this->bundle['activation']);
        $proofName = 'dendrila-cle-' . $this->bundle['id'] . '.txt';
        $activationHash = $installed ? null : ($this->bundle['activation']
            ?? $this->readProof(dirname($standaloneDirectory) . '/' . $proofName));
        if ($method === 'POST') {
            $token = $_POST[$installed ? 'retro_token' : 'csrf'] ?? null;
            if (!is_string($token) || !hash_equals($csrf, $token)) {
                http_response_code(403);
                echo 'Le formulaire a expiré. Recharge la page puis réessaie.';
                return false;
            }
            if ($signedIn) {
                $_SESSION = [];
                session_regenerate_id(true);
                header('Location: oldweb-editor.php', true, 303);
                return false;
            }

            try {
                if (!$installed && !$personal && $action === 'Télécharger le fichier de clé') {
                    $key = $this->chosenKey($_POST['activation'] ?? null);
                    header('Content-Type: text/plain; charset=UTF-8');
                    header('Content-Disposition: attachment; filename="' . $proofName . '"');
                    echo $this->proofHash($key) . "\n";
                    return false;
                }
                $password = $_POST['password'] ?? null;
                if (!is_string($password) || strlen($password) > 72 || str_contains($password, "\0")) {
                    throw new InvalidArgumentException('Le mot de passe est trop long ou incorrect.');
                }

                if ($installed) {
                    if (!$this->allowAttempt($standaloneDirectory)) {
                        http_response_code(429);
                        header('Retry-After: 300');
                        throw new InvalidArgumentException('Trop de tentatives. Réessaie dans quelques minutes.');
                    }
                    if (!password_verify($password, $hash)) {
                        http_response_code(403);
                        throw new InvalidArgumentException('Mot de passe incorrect.');
                    }
                } else {
                    $submitted = is_array($_POST['site'] ?? null) ? $_POST['site'] : [];
                    $key = $_POST['activation'] ?? null;
                    if ($activationHash === null) {
                        throw new InvalidArgumentException('Téléverse d’abord le fichier de clé dans ton dossier personnel cPanel.');
                    }
                    $candidate = $personal
                        ? (is_string($key) && strlen(trim($key)) === 32 ? hash('sha256', trim($key)) : '')
                        : $this->proofHash($this->chosenKey($key));
                    if (!hash_equals($activationHash, $candidate)) {
                        throw new InvalidArgumentException($personal
                            ? 'La clé d’installation ne correspond pas. Copie la clé du fichier LIRE-MOI.txt.'
                            : 'La clé d’installation ne correspond pas au fichier déposé dans cPanel.');
                    }
                    if (mb_strlen($password, 'UTF-8') < 14) {
                        throw new InvalidArgumentException('Choisis un mot de passe d’au moins 14 caractères et de 72 octets au maximum.');
                    }
                    if (!is_dir($standaloneDirectory) && !@mkdir($standaloneDirectory, 0700) && !is_dir($standaloneDirectory)) {
                        throw new RuntimeException('Dendrila Retro ne parvient pas à créer son espace privé dans ton compte d’hébergement.');
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $store = new PortalStorage($standaloneDirectory, $_SERVER['DOCUMENT_ROOT']);
                    if (!$store->change(0, 'install', $submitted, [], PortalThemes::selected($_POST['theme'] ?? null), $hash)) {
                        http_response_code(409);
                        header('Location: oldweb-editor.php', true, 303);
                        return false;
                    }
                }

                session_regenerate_id(true);
                $_SESSION = ['retro_token' => bin2hex(random_bytes(32)), 'retro_user' => hash('sha256', $hash)];
                header('Location: oldweb-editor.php' . ($installed ? '' : '?result=install'), true, 303);
                return false;
            } catch (InvalidArgumentException $exception) {
                if (http_response_code() < 400) {
                    http_response_code(422);
                }
                $error = $exception->getMessage();
            }
        }

        if (!$installed && !$personal && $activationHash === null && $error === ''
            && ($_GET['verifier'] ?? '') === '1') {
            $error = 'Le fichier de clé est introuvable ou illisible. Vérifie le dossier et le nom du fichier dans cPanel.';
        }
        if ($method !== 'HEAD') {
            $template = $installed ? 'standalone-login.php' : 'portal-install.php';
            if (!$installed && !$personal && $activationHash === null) {
                $template = 'standalone-activation.php';
            }
            require __DIR__ . '/' . $template;
        }
        return false;
    }

    private function chosenKey(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 128 || strlen($value) < 24
            || trim($value) !== $value || preg_match('/^[\x20-\x7E]+$/D', $value) !== 1) {
            throw new InvalidArgumentException('Choisis une clé de 24 à 128 caractères, avec des lettres sans accent, des chiffres ou des symboles et sans espace au début ou à la fin.');
        }
        return $value;
    }

    private function proofHash(string $key): string
    {
        return hash('sha256', $this->bundle['id'] . "\0" . $key);
    }

    private function readProof(string $path): ?string
    {
        if (is_link($path) || !is_file($path)) {
            return null;
        }
        $value = @file_get_contents($path, false, null, 0, 67);
        return is_string($value) && preg_match('/^[a-f0-9]{64}(?:\r?\n)?$/D', $value) === 1
            ? trim($value) : null;
    }

    private function allowAttempt(string $directory): bool
    {
        $file = fopen($directory . '/login-attempts.json', 'c+');
        if ($file === false) {
            throw new RuntimeException('Le portail ne peut pas enregistrer les tentatives de connexion.');
        }
        try {
            if (!flock($file, LOCK_EX)) {
                throw new RuntimeException('Le portail ne peut pas contrôler les tentatives de connexion.');
            }
            $now = time();
            $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
            $previous = json_decode((string) stream_get_contents($file), true) ?? [];
            $attempts = array_filter($previous, static fn (array $entry): bool => $entry[0] > $now);
            $entry = $attempts[$ip] ?? [$now + 300, 0];
            if ($entry[1] >= 8 || (!isset($attempts[$ip]) && count($attempts) >= 256)) {
                return false;
            }
            $attempts[$ip] = [$entry[0], $entry[1] + 1];
            $json = json_encode($attempts, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                throw new RuntimeException('Le portail ne peut pas enregistrer les tentatives de connexion.');
            }
            return true;
        } finally {
            fclose($file);
        }
    }
}
