<?php
declare(strict_types=1);

namespace Dendrila\Cms\Installation;

/**
 * A hosting-file-manager alternative to prepare-install.php.
 *
 * Only someone who can write to the private config directory can complete the
 * challenge. The challenge must never be stored under the web document root.
 */
final class WebOwnerProof
{
    public const FILENAME = 'ownership-proof.txt';

    public static function createChallenge(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function confirm(string $configDirectory, string $challenge): bool
    {
        if (preg_match('/^[a-f0-9]{48}$/D', $challenge) !== 1) {
            return false;
        }
        $directory = realpath($configDirectory);
        if ($directory === false || !is_dir($directory)
            || is_link($configDirectory)
            || is_file($directory.'/local.php')) {
            return false;
        }
        $file = $directory.'/'.self::FILENAME;
        if (is_link($file) || !is_file($file) || !is_readable($file)
            || filesize($file) === false || filesize($file) > 128) {
            return false;
        }
        $stored = file_get_contents($file);
        if (!is_string($stored) || !hash_equals($challenge, trim($stored))) {
            return false;
        }
        // Consume the proof before granting the owner an authorized session.
        return unlink($file);
    }
}
