<?php
declare(strict_types=1);

namespace Dendrila\Cms\Seo;

final class SeoMetadataDefinition
{
    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    public static function normalize(array $metadata): array
    {
        $allowed = [
            'title', 'description', 'canonical_url',
            'social_title', 'social_description', 'social_image_url',
            'indexable',
        ];
        if (array_diff(array_keys($metadata), $allowed) !== []) {
            throw new \InvalidArgumentException('SEO metadata contains unsupported fields.');
        }

        $normalized = [
            'title' => self::optionalText($metadata['title'] ?? null, 190, 'SEO title'),
            'description' => self::optionalText($metadata['description'] ?? null, 500, 'SEO description'),
            'canonical_url' => self::optionalUrl($metadata['canonical_url'] ?? null, true, 'canonical URL'),
            'social_title' => self::optionalText($metadata['social_title'] ?? null, 190, 'social title'),
            'social_description' => self::optionalText($metadata['social_description'] ?? null, 500, 'social description'),
            'social_image_url' => self::optionalUrl($metadata['social_image_url'] ?? null, true, 'social image URL'),
            'indexable' => $metadata['indexable'] ?? true,
        ];
        if (!is_bool($normalized['indexable'])) {
            throw new \InvalidArgumentException('SEO indexable flag must be boolean.');
        }
        return $normalized;
    }

    private static function optionalText(mixed $value, int $max, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > $max) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function optionalUrl(mixed $value, bool $allowRootRelative, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        $url = trim($value);
        if ($url === '' || str_contains($url, "\0") || str_starts_with($url, '//')) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        if ($allowRootRelative && str_starts_with($url, '/')) {
            return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
            return $url;
        }
        throw new \InvalidArgumentException("{$label} must be root-relative or HTTPS.");
    }
}
