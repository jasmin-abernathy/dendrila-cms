<?php
declare(strict_types=1);
namespace Dendrila\Cms\Forum;

final class ForumRules
{
    public const MAX_PHOTOS_PER_MESSAGE = 4;
    public const MAX_UPLOAD_BYTES = 6_291_456;

    public static function title(string $value): string { return self::text($value, 4, 160, 'Titre'); }
    public static function message(string $value): string { return self::text($value, 2, 10_000, 'Message'); }
    public static function alt(string $value): string { return self::text($value, 2, 240, 'Description de la photo'); }

    private static function text(string $value, int $min, int $max, string $label): string
    {
        $value = trim(str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], $value));
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') < $min ||
            mb_strlen($value, 'UTF-8') > $max) {
            throw new \InvalidArgumentException($label . ' : longueur ou encodage invalide.');
        }
        return $value; // Contenu brut : l'interface échappe tout HTML.
    }

    public static function id(string $id): string
    {
        if (!preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw new \InvalidArgumentException('Identifiant de forum invalide.');
        }
        return $id;
    }

    public static function site(string $id): string
    {
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/D', $id)) {
            throw new \InvalidArgumentException('Identifiant de site invalide.');
        }
        return $id;
    }
}
