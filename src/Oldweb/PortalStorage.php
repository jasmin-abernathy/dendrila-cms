<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

use InvalidArgumentException;
use RuntimeException;

final readonly class PortalStorage
{
    public const MIMES = ['gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg'];
    public const MAX_UPLOAD = 1048576;
    private const MAX_ASSETS = 32 * self::MAX_UPLOAD;
    private string $directory;

    public function __construct(string $directory, string $documentRoot)
    {
        $root = realpath($documentRoot);
        $path = realpath($directory);
        if ($root === false || $path === false || !is_dir($path) || !is_writable($path)
            || $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR)
            || str_starts_with($root, $path . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Portal storage requires a dedicated private directory.');
        }
        $this->directory = $path;
    }

    public function state(): array
    {
        $path = $this->directory . '/appearance.json';
        $themes = ['draft' => PortalThemes::DEFAULT, 'published' => PortalThemes::DEFAULT, 'previous' => PortalThemes::DEFAULT];
        if (!is_file($path)) {
            $defaults = PortalAppearance::defaults();
            return [
                'revision' => 0, 'published' => $defaults, 'draft' => $defaults, 'previous' => null,
                'site' => [], 'themes' => $themes,
            ];
        }
        return json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR)
            + ['site' => [], 'themes' => $themes];
    }


    /**
     * Check the server-received upload from each allowed slot. The filename is ignored.
     * @return array<string,array{name:string,bytes:string}>
     */
    public static function uploads(array $files): array
    {
        $result = [];
        foreach ($files as $field => $file) {
            $key = substr($field, 6);
            if (!str_starts_with($field, 'image_') || (PortalAppearance::ZONES[$key][3] ?? 0) === 0
                || !is_array($file)) {
                throw new InvalidArgumentException('Zone de téléversement inconnue.');
            }
            $status = $file['error'] ?? null;
            if ($status === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $tmp = $file['tmp_name'] ?? null;
            if ($status !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)
                || filesize($tmp) > self::MAX_UPLOAD) {
                throw new InvalidArgumentException('Le fichier doit être une image de 1 Mio maximum.');
            }
            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new RuntimeException('Cannot read portal upload.');
            }
            $result[$key] = ['name' => self::assetName($bytes), 'bytes' => $bytes];
        }
        return $result;
    }

    public static function assetName(string $bytes): string
    {
        if (strlen($bytes) > self::MAX_UPLOAD) {
            throw new InvalidArgumentException('Le fichier dépasse 1 Mio.');
        }
        $image = @getimagesizefromstring($bytes);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = array_search($mime, self::MIMES, true);
        if ($extension === false || ($image['mime'] ?? null) !== $mime
            || $image[0] < 1 || $image[1] < 1 || $image[0] > 1600 || $image[1] > 1600) {
            throw new InvalidArgumentException('Choisis un GIF, PNG ou JPEG valide de 1 600 px maximum par côté.');
        }
        return hash('sha256', $bytes) . '.' . $extension;
    }

    /** Return false when another editor has saved a more recent version. */
    public function change(int $expected, string $action, array $fields, array $uploads, ?string $theme = null): bool
    {
        $lock = fopen($this->directory . '/appearance.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open portal lock.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot acquire portal lock.');
            }
            $state = $this->state();
            if ($state['revision'] !== $expected) {
                return false;
            }
            if ($action === 'install' && $state['revision'] === 0) {
                $state['site'] = self::siteIdentity($fields);
                $id = PortalThemes::selected($theme ?? PortalThemes::DEFAULT);
                $state['themes']['draft'] = $state['themes']['published'] = $id;
                $state['draft'] = $state['published'] = PortalThemes::initialAppearance($id);
            } elseif ($action === 'restore' && is_array($state['previous'])) {
                $state['draft'] = $state['previous'];
                $state['previous'] = $state['published'];
                $state['published'] = $state['draft'];
                $themes = $state['themes'];
                $state['themes'] = ['draft' => $themes['previous'], 'published' => $themes['previous'], 'previous' => $themes['published']];
            } elseif (in_array($action, ['draft', 'publish'], true)) {
                $state['draft'] = PortalAppearance::edit($fields, $state['draft'], array_map(static fn (array $upload): string => $upload['name'], $uploads));
                $state['themes']['draft'] = PortalThemes::selected($theme ?? $state['themes']['draft']);
                $this->storeImages($uploads);
                if ($action === 'publish') {
                    $state['themes']['previous'] = $state['themes']['published'];
                    $state['themes']['published'] = $state['themes']['draft'];
                    $state['previous'] = $state['published'];
                    $state['published'] = $state['draft'];
                }
            } else {
                throw new InvalidArgumentException('Action indisponible.');
            }
            $state['revision']++;
            $this->atomicWrite('appearance.json', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            return true;
        } finally {
            fclose($lock);
        }
    }

    private static function siteIdentity(array $fields): array
    {
        $identity = [];
        foreach (['name' => 55, 'tagline' => 100] as $key => $maximum) {
            $value = $fields[$key] ?? null;
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || preg_match('/[[:cntrl:]]/', $value) !== 0 || mb_strlen($value, 'UTF-8') > $maximum
                || ($key === 'name' && trim($value) === '')) {
                throw new InvalidArgumentException('Indique un nom de 1 à 55 caractères et un sous-titre de 100 caractères au plus.');
            }
            $identity[$key] = trim($value);
        }
        return $identity;
    }

    private function storeImages(array $uploads): void
    {
        $used = 0;
        foreach (glob($this->directory . '/portal-*') ?: [] as $path) {
            $used += filesize($path);
        }
        $pending = [];
        foreach ($uploads as $upload) {
            $name = $upload['name'];
            if (isset($pending[$name]) || is_file($this->directory . '/portal-' . $name)) {
                continue;
            }
            $used += strlen($upload['bytes']);
            if ($used > self::MAX_ASSETS) {
                throw new InvalidArgumentException('L’espace des images Oldweb est plein.');
            }
            $pending[$name] = $upload['bytes'];
        }
        foreach ($pending as $name => $bytes) {
            $this->atomicWrite('portal-' . $name, $bytes);
        }
    }

    /** @return array{path:string,mime:string}|null */
    public function image(string $name, array $appearance): ?array
    {
        if (preg_match('/^[a-f0-9]{64}\.(gif|png|jpg)$/D', $name, $parts) !== 1
            || !in_array($name, array_column($appearance, 'image'), true)) {
            return null;
        }
        $path = $this->directory . '/portal-' . $name;
        return is_file($path) ? ['path' => $path, 'mime' => self::MIMES[$parts[1]]] : null;
    }

    private function atomicWrite(string $name, string $bytes): void
    {
        $temporary = tempnam($this->directory, '.oldweb-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot stage portal data.');
        }
        try {
            if (file_put_contents($temporary, $bytes) !== strlen($bytes)
                || !rename($temporary, $this->directory . '/' . $name)) {
                throw new RuntimeException('Cannot save portal data.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
