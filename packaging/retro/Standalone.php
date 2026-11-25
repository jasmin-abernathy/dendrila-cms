<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

use InvalidArgumentException;
use RuntimeException;

final readonly class Standalone
{
    public function __construct(private array $bundle)
    {
        if (!is_string($bundle['id'] ?? null) || !preg_match('/^[a-f0-9]{24}$/D', $bundle['id'])
            || (array_key_exists('activation', $bundle)
                && (!is_string($bundle['activation']) || !preg_match('/^[a-f0-9]{64}$/D', $bundle['activation'])))) {
            throw new RuntimeException('La configuration privée du kit est invalide.');
        }
    }

    public function directory(string $documentRoot, string $home): string
    {
        $root = $documentRoot === '' ? false : realpath($documentRoot);
        $configured = getenv('DENDRILA_RETRO_PRIVATE_HOME');
        if (is_string($configured) && $configured !== '') {
            $home = $configured;
        } elseif (is_string($root) && preg_match('~^(/home[0-9]*/[^/]+)/~D', $root, $parts) === 1) {
            // cPanel can omit HOME in FastCGI.
            $home = $parts[1];
        }
        $home = $home !== '' ? realpath($home) : false;
        if (!is_string($root) || !is_string($home) || $home === '/' || $home === $root
            || !is_dir($home) || str_starts_with($home, $root . DIRECTORY_SEPARATOR)
            || (($configured === false || $configured === '') && !str_starts_with($root, $home . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Dendrila Retro ne trouve pas de dossier privé hors du dossier web. Vérifie le guide de ton hébergement.');
        }
        $directory = $home . '/.dendrila-retro-' . $this->bundle['id'];
        if (is_link($directory) || (file_exists($directory) && (!is_dir($directory)
            || !is_writable($directory) || (fileperms($directory) & 0077) !== 0))
            || (!is_dir($directory) && !is_writable($home))
            || $directory === $root || str_starts_with($root, $directory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Le dossier privé de Dendrila Retro doit être accessible en écriture et réservé à ton compte.');
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

        $statePath = $standaloneDirectory . '/appearance.json';
        if (is_link($statePath) || (file_exists($statePath) && !is_file($statePath))) {
            throw new RuntimeException('Dendrila Retro ne peut pas lire son état privé.');
        }
        $installed = is_file($statePath);
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
        if (!session_start(['use_strict_mode' => 1, 'use_only_cookies' => 1, 'cookie_secure' => true,
            'cookie_httponly' => true, 'cookie_samesite' => 'Strict'])) {
            throw new RuntimeException('Dendrila Retro ne peut pas ouvrir de session sur cet hébergement.');
        }

        $store = $installed ? new PortalStorage($standaloneDirectory, $_SERVER['DOCUMENT_ROOT']) : null;
        $state = $store?->state() ?? [];
        $hash = $state['owner_password_hash'] ?? '';
        $ownerUsername = $state['owner_username'] ?? null; // Existing installations sign in with a password alone.
        if ($installed && (!is_string($hash) || $hash === '' || ($ownerUsername !== null && !is_string($ownerUsername)))) {
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
        $submittedUsername = '';
        $personal = isset($this->bundle['activation']);
        $proofName = 'dendrila-cle-' . $this->bundle['id'] . '.txt';
        $proofPath = dirname($standaloneDirectory) . '/' . $proofName;
        $policy = getenv('DENDRILA_RETRO_REQUIRE_KEY');
        // Even unreadable or malformed legacy proof files must keep the setup locked.
        $requiresKey = !$installed && ($personal || is_link($proofPath) || file_exists($proofPath)
            || (is_string($policy) && $policy !== '' && $policy !== '0'));
        $activationHash = $requiresKey ? ($this->bundle['activation'] ?? $this->readProof($proofPath)) : null;

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
                if ($requiresKey && !$personal && $action === 'Télécharger le fichier de clé') {
                    $key = $this->chosenKey($_POST['activation'] ?? null);
                    header('Content-Type: text/plain; charset=UTF-8');
                    header('Content-Disposition: attachment; filename="' . $proofName . '"');
                    echo $this->proofHash($key) . "\n";
                    return false;
                }

                $username = $_POST['username'] ?? '';
                $submittedUsername = is_string($username) ? substr($username, 0, 32) : '';
                $password = $_POST['password'] ?? null;
                if ($installed) {
                    if ($action !== 'Se connecter') {
                        http_response_code(409);
                        throw new InvalidArgumentException('Ce portail est déjà installé.');
                    }
                    $this->checkAttempt($standaloneDirectory, 'login');
                    // Verify the password even if the username is wrong to avoid a timing distinction.
                    $validPassword = is_string($password) && strlen($password) <= 72
                        && !str_contains($password, "\0") && password_verify($password, $hash);
                    if (!$validPassword || ($ownerUsername !== null
                        && (!is_string($username) || !hash_equals($ownerUsername, $username)))) {
                        http_response_code(403);
                        throw new InvalidArgumentException('Identifiant ou mot de passe incorrect.');
                    }
                } else {
                    $submitted = is_array($_POST['site'] ?? null) ? $_POST['site'] : [];
                    $submittedTheme = PortalThemes::selected($_POST['theme'] ?? null);
                    if ($action !== 'Installer Dendrila Retro') {
                        throw new InvalidArgumentException('Action d’installation inconnue.');
                    }
                    if ($requiresKey && $activationHash === null) {
                        throw new InvalidArgumentException('Le mode protégé exige un fichier de clé valide dans le dossier privé.');
                    }
                    if (!is_string($username) || !preg_match('/^[A-Za-z0-9._-]{3,32}$/D', $username)) {
                        throw new InvalidArgumentException('L’identifiant doit faire de 3 à 32 caractères, avec lettres sans accent, chiffres, points, tirets ou tirets bas.');
                    }
                    if (!is_string($password) || strlen($password) > 72 || str_contains($password, "\0")
                        || !mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 14) {
                        throw new InvalidArgumentException('Choisis un mot de passe d’au moins 14 caractères et de 72 octets au maximum.');
                    }
                    if ($password !== ($_POST['password_confirmation'] ?? null)) {
                        throw new InvalidArgumentException('Les deux mots de passe doivent être identiques.');
                    }
                    $this->ensureDirectory($standaloneDirectory);
                    $this->checkAttempt($standaloneDirectory, 'setup');
                    if ($activationHash !== null) {
                        $key = $_POST['activation'] ?? null;
                        $candidate = $personal
                            ? (is_string($key) && preg_match('/^[a-f0-9]{32}$/D', trim($key)) ? hash('sha256', trim($key)) : '')
                            : $this->proofHash($this->chosenKey($key));
                        if (!hash_equals($activationHash, $candidate)) {
                            throw new InvalidArgumentException('La clé d’installation ne correspond pas au kit ou au fichier de clé.');
                        }
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $store = new PortalStorage($standaloneDirectory, $_SERVER['DOCUMENT_ROOT']);
                    if (!$store->change(0, 'install', $submitted, [], $submittedTheme, $hash, $username)) {
                        http_response_code(409);
                        echo 'Ce portail vient d’être installé. <a href="oldweb-editor.php">Ouvrir la connexion</a>.';
                        return false;
                    }
                }

                if (!session_regenerate_id(true)) {
                    throw new RuntimeException('Recharge la page pour te connecter au portail.');
                }
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

        if ($method !== 'HEAD') {
            $template = $installed ? 'standalone-login.php'
                : ($requiresKey && !$personal && $activationHash === null ? 'standalone-activation.php' : 'portal-install.php');
            require __DIR__ . '/' . $template;
        }
        return false;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700)) {
            clearstatcache(true, $directory);
        }
        if (is_link($directory) || !is_dir($directory) || (fileperms($directory) & 0077) !== 0) {
            throw new RuntimeException('Dendrila Retro ne parvient pas à créer son dossier privé.');
        }
        $deny = $directory . '/.htaccess';
        if (!file_exists($deny)) {
            $handle = @fopen($deny, 'x');
            if ($handle !== false) {
                fwrite($handle, "Require all denied\n");
                fclose($handle);
            }
        }
    }

    private function checkAttempt(string $directory, string $scope): void
    {
        if (!$this->allowAttempt($directory, $scope)) {
            http_response_code(429);
            header('Retry-After: 300');
            throw new InvalidArgumentException('Trop de tentatives. Réessaie dans quelques minutes.');
        }
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

    private function allowAttempt(string $directory, string $scope): bool
    {
        $file = fopen($directory . '/' . $scope . '-attempts.json', 'c+');
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
