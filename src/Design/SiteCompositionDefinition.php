<?php
declare(strict_types=1);

namespace Dendrila\Cms\Design;

use InvalidArgumentException;

final readonly class SiteCompositionDefinition
{
    private const COLOR_KEYS = [
        'primary', 'secondary', 'primary_strong', 'background', 'surface',
        'text', 'muted', 'accent_soft', 'border',
    ];
    private const URL_KEYS = ['logo_url', 'wordmark_url'];
    private const FONT_KEYS = ['heading_font', 'body_font'];
    private const FONT_CHOICES = [
        'neutral-sans', 'humanist-sans', 'editorial-serif', 'system-serif',
    ];

    /** @param array<string, mixed> $definition */
    public function __construct(private array $definition)
    {
        if (($definition['schema_version'] ?? null) !== 1
            || !self::identifier($definition['functional_kit'] ?? null)
            || !self::identifier($definition['design_style'] ?? null)) {
            throw new InvalidArgumentException('Invalid site composition.');
        }

        $modules = $definition['enabled_modules'] ?? [];
        if (!is_array($modules) || !array_is_list($modules) || count($modules) > 100) {
            throw new InvalidArgumentException('Invalid enabled_modules list.');
        }
        $seen = [];
        foreach ($modules as $module) {
            if (!is_string($module) || preg_match('/^[a-z][a-z0-9_-]*$/', $module) !== 1 || isset($seen[$module])) {
                throw new InvalidArgumentException('Invalid or duplicate enabled module.');
            }
            $seen[$module] = true;
        }

        $brand = $definition['brand_overrides'] ?? [];
        if (!is_array($brand) || ($brand !== [] && array_is_list($brand))) {
            throw new InvalidArgumentException('Brand overrides must be an object.');
        }
        $allowed = array_merge(self::COLOR_KEYS, self::URL_KEYS, self::FONT_KEYS);
        if (array_diff(array_keys($brand), $allowed) !== []) {
            throw new InvalidArgumentException('Unknown brand override.');
        }
        foreach (self::COLOR_KEYS as $key) {
            if (array_key_exists($key, $brand)
                && (!is_string($brand[$key]) || preg_match('/^#[0-9A-Fa-f]{6}$/', $brand[$key]) !== 1)) {
                throw new InvalidArgumentException("Invalid brand color '{$key}'.");
            }
        }
        foreach (self::URL_KEYS as $key) {
            if (array_key_exists($key, $brand) && !self::httpsUrl($brand[$key])) {
                throw new InvalidArgumentException("Invalid brand asset URL '{$key}'.");
            }
        }
        foreach (self::FONT_KEYS as $key) {
            if (array_key_exists($key, $brand)
                && (!is_string($brand[$key]) || !in_array($brand[$key], self::FONT_CHOICES, true))) {
                throw new InvalidArgumentException("Invalid brand font '{$key}'.");
            }
        }

        $styleOverrides = $definition['style_overrides'] ?? [];
        if (!is_array($styleOverrides) || ($styleOverrides !== [] && array_is_list($styleOverrides))) {
            throw new InvalidArgumentException('Style overrides must be an object.');
        }
    }

    public function functionalKitId(): string { return $this->definition['functional_kit']; }
    public function designStyleId(): string { return $this->definition['design_style']; }

    /** @return list<string> */
    public function enabledModules(): array { return $this->definition['enabled_modules'] ?? []; }

    /** @return array<string, string> */
    public function brandOverrides(): array { return $this->definition['brand_overrides'] ?? []; }

    /** @return array<string, mixed> */
    public function styleOverrides(): array { return $this->definition['style_overrides'] ?? []; }

    private static function identifier(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z][a-z0-9-]*$/', $value) === 1;
    }

    private static function httpsUrl(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';
    }
}
