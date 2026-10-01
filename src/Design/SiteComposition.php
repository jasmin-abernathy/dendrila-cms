<?php
declare(strict_types=1);

namespace LePotager\Cms\Design;

final readonly class SiteComposition
{
    public DesignStyleDefinition $style;

    /** Catalogs are supplied by the server, never by the request. */
    public function __construct(private array $data, array $styles, array $kits, array $modules)
    {
        if (array_diff(array_keys($data), ['schema_version','functional_kit','design_style','enabled_modules','brand_overrides','style_overrides']) !== []
            || ($data['schema_version'] ?? null) !== 1
            || !is_string($data['functional_kit'] ?? null)
            || !in_array($data['functional_kit'], $kits, true)
            || !is_string($data['design_style'] ?? null)
            || !isset($styles[$data['design_style']])) {
            throw new \InvalidArgumentException('Invalid site composition or unknown catalog entry.');
        }
        $enabled = $data['enabled_modules'] ?? [];
        if (!is_array($enabled) || !array_is_list($enabled) || count($enabled) > 100) {
            throw new \InvalidArgumentException('Invalid enabled modules.');
        }
        $seen = [];
        foreach ($enabled as $module) {
            if (!is_string($module) || !in_array($module, $modules, true) || isset($seen[$module])) {
                throw new \InvalidArgumentException('Unknown or duplicate module.');
            }
            $seen[$module] = true;
        }
        $brand = $data['brand_overrides'] ?? [];
        if (!is_array($brand) || array_diff(array_keys($brand), ['logo_url','primary','secondary','background','text']) !== []) {
            throw new \InvalidArgumentException('Unknown brand override.');
        }
        foreach ($brand as $key => $value) {
            if (!is_string($value)) throw new \InvalidArgumentException('Invalid brand value.');
            if ($key === 'logo_url') {
                if (strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false
                    || !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http','https'], true)
                    || parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null) {
                    throw new \InvalidArgumentException('Logo must be a public HTTP(S) URL.');
                }
            } elseif (preg_match('/^#[0-9a-fA-F]{6}$/D', $value) !== 1) {
                throw new \InvalidArgumentException('Brand colors must be six-digit hex values.');
            }
        }
        // Validate the actual resulting text/background pair, including defaults.
        $text = $brand['text'] ?? '#252b28';
        $background = $brand['background'] ?? '#ffffff';
        $ratio = self::contrast($text, $background);
        $baseStyle = $styles[$data['design_style']];
        if (!$baseStyle instanceof DesignStyleDefinition) throw new \InvalidArgumentException('Invalid server style catalog.');
        $target = $baseStyle->contrastTarget() === 'AAA' ? 7.0 : 4.5;
        if ($ratio < $target) throw new \InvalidArgumentException('Brand text/background contrast is insufficient.');
        $overrides = $data['style_overrides'] ?? [];
        if (!is_array($overrides)) throw new \InvalidArgumentException('Invalid style overrides.');
        $this->style = $baseStyle->withOverrides($overrides);
    }

    public function toArray(): array { return $this->data; }

    public function json(): string
    {
        $data = $this->data;
        if (isset($data['brand_overrides'])) $data['brand_overrides'] = (object) $data['brand_overrides'];
        if (isset($data['style_overrides'])) {
            $groups = [];
            foreach ($data['style_overrides'] as $group => $values) $groups[$group] = (object) $values;
            $data['style_overrides'] = (object) $groups;
        }
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function contrast(string $a, string $b): float
    {
        $luminance = static function (string $hex): float {
            $sum = 0.0;
            foreach ([0.2126, 0.7152, 0.0722] as $i => $weight) {
                $v = hexdec(substr($hex, 1 + $i * 2, 2)) / 255;
                $sum += $weight * ($v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4);
            }
            return $sum;
        };
        $x = $luminance($a); $y = $luminance($b);
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }
}
