<?php
declare(strict_types=1);

namespace Dendrila\Cms\Design;

use InvalidArgumentException;
use RuntimeException;

final readonly class PresentationResolver
{
    private string $designStylesDirectory;

    public function __construct(
        string $designStylesDirectory,
        private DesignKitCatalog $catalog,
        private CssTokenCompiler $styleCompiler = new CssTokenCompiler(),
        private BrandTokenCompiler $brandCompiler = new BrandTokenCompiler(),
    ) {
        $resolved = realpath($designStylesDirectory);
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Design styles directory does not exist.');
        }
        $this->designStylesDirectory = $resolved;
    }

    /** @return array<string, mixed> */
    public function resolve(SiteCompositionDefinition $composition): array
    {
        if ($composition->styleOverrides() !== []) {
            throw new InvalidArgumentException('Style overrides are not wired yet; use a declared design style preset.');
        }

        $style = $this->loadStyle($composition->designStyleId());
        $theme = $this->catalog->themeForStyle($style->id());
        $styleVariables = $this->styleCompiler->variables($style);

        foreach ($theme['required_style_tokens'] as $token) {
            if (!array_key_exists($token, $styleVariables)) {
                throw new RuntimeException("Installed design kit requires unsupported style token '{$token}'.");
            }
        }

        $brand = $composition->brandOverrides();
        $variables = array_merge($styleVariables, $this->brandCompiler->variables($brand));
        $selector = '[data-dendrila-theme="' . $theme['id'] . '"]';

        return [
            'schema_version' => 1,
            'functional_kit' => $composition->functionalKitId(),
            'design_style' => $style->id(),
            'design_kit' => [
                'id' => $theme['id'],
                'version' => $theme['version'],
                'root_path' => $theme['root_path'],
                'entrypoint' => $theme['entrypoint'],
                'entrypoint_path' => $theme['entrypoint_path'],
                'components' => $theme['components'],
            ],
            'enabled_modules' => $composition->enabledModules(),
            'structure' => [
                'hero' => $style->token('layout.hero'),
                'alignment' => $style->token('layout.alignment'),
                'gallery' => $style->token('media.gallery'),
                'media_crop' => $style->token('media.crop'),
                'full_bleed' => $style->token('media.full_bleed'),
                'accent_backgrounds' => $style->token('accent.backgrounds'),
                'illustration_frequency' => $style->token('illustration.frequency'),
                'illustration_style' => $style->token('illustration.style'),
                'heading_style' => $style->token('typography.heading_style'),
                'body_style' => $style->token('typography.body_style'),
            ],
            'brand_assets' => $this->brandCompiler->assets($brand),
            'css_variables' => $variables,
            'compiled_css' => $this->compileVariables($selector, $variables),
            'media' => [
                'default_shape' => 'auto',
                'supported_shapes' => $theme['media_shapes'],
                'focal_min' => 0,
                'focal_max' => 100,
            ],
        ];
    }

    private function loadStyle(string $id): DesignStyleDefinition
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $id) !== 1) {
            throw new InvalidArgumentException('Unsafe design style id.');
        }
        $directory = realpath($this->designStylesDirectory . DIRECTORY_SEPARATOR . $id);
        if ($directory === false || !is_dir($directory)
            || !str_starts_with($directory, $this->designStylesDirectory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Design style '{$id}' is not installed.");
        }
        $path = realpath($directory . DIRECTORY_SEPARATOR . 'style.json');
        if ($path === false || !is_file($path) || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Design style '{$id}' has no readable definition.");
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException("Design style '{$id}' contains invalid JSON.", previous: $error);
        }
        if (!is_array($data)) {
            throw new RuntimeException("Design style '{$id}' must be a JSON object.");
        }
        $style = new DesignStyleDefinition($data);
        if ($style->id() !== $id) {
            throw new RuntimeException('Design style directory and definition id differ.');
        }
        return $style;
    }

    /** @param array<string, string> $variables */
    private function compileVariables(string $selector, array $variables): string
    {
        $lines = [$selector . ' {'];
        foreach ($variables as $name => $value) {
            if (preg_match('/^--dendrila-[a-z0-9-]+$/', $name) !== 1
                || str_contains($value, '{') || str_contains($value, '}') || str_contains($value, ';')) {
                throw new RuntimeException('Unsafe compiled presentation variable.');
            }
            $lines[] = '  ' . $name . ': ' . $value . ';';
        }
        $lines[] = '}';
        return implode("\n", $lines) . "\n";
    }
}
