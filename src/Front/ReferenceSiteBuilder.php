<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use Dendrila\Cms\Design\PresentationResolver;
use Dendrila\Cms\Design\SiteCompositionDefinition;
use InvalidArgumentException;
use RuntimeException;

final readonly class ReferenceSiteBuilder
{
    public function __construct(
        private PresentationResolver $presentationResolver,
        private PublicPageRenderer $renderer = new PublicPageRenderer(),
    ) {}

    /**
     * @param array<string,mixed> $fixture
     * @return array{output_directory:string,index_path:string,theme_directory:string,media_directory:string}
     */
    public function build(
        SiteCompositionDefinition $composition,
        array $fixture,
        string $referenceMediaDirectory,
        string $outputDirectory,
    ): array {
        $presentation = $this->presentationResolver->resolve($composition);
        $designKit = $presentation['design_kit'] ?? null;
        if (!is_array($designKit)
            || !is_string($designKit['root_path'] ?? null)
            || !is_string($designKit['entrypoint'] ?? null)) {
            throw new RuntimeException('Resolved design kit cannot be materialized.');
        }

        $this->prepareEmptyDirectory($outputDirectory);

        $themeTarget = $outputDirectory . DIRECTORY_SEPARATOR . 'theme';
        $mediaTarget = $outputDirectory . DIRECTORY_SEPARATOR . 'media';
        $this->copyDirectory($designKit['root_path'], $themeTarget);
        $this->copyDirectory($referenceMediaDirectory, $mediaTarget);

        $site = $fixture['site'] ?? null;
        $page = $fixture['page'] ?? null;
        $mediaUrls = $fixture['media_urls'] ?? [];
        $navigation = $fixture['navigation'] ?? [];
        if (!is_array($site) || !is_array($page) || !is_array($mediaUrls) || !is_array($navigation)) {
            throw new InvalidArgumentException('Reference site fixture is incomplete.');
        }

        $entrypoint = str_replace(DIRECTORY_SEPARATOR, '/', $designKit['entrypoint']);
        $themeCssUrl = '/theme/' . ltrim($entrypoint, '/');
        $html = $this->renderer->render(
            $site,
            $page,
            $presentation,
            $themeCssUrl,
            $mediaUrls,
            $navigation,
        );

        $indexPath = $outputDirectory . DIRECTORY_SEPARATOR . 'index.html';
        if (file_put_contents($indexPath, $html) === false) {
            throw new RuntimeException('Unable to write the reference site HTML.');
        }

        $readme = implode("\n", [
            'Dendrila reference site',
            '',
            'Serve this directory from its root, for example:',
            'php -S 127.0.0.1:8080 -t .',
            '',
            'Then open http://127.0.0.1:8080/',
            '',
        ]);
        if (file_put_contents($outputDirectory . DIRECTORY_SEPARATOR . 'README.txt', $readme) === false) {
            throw new RuntimeException('Unable to write the reference site instructions.');
        }

        return [
            'output_directory' => $outputDirectory,
            'index_path' => $indexPath,
            'theme_directory' => $themeTarget,
            'media_directory' => $mediaTarget,
        ];
    }

    private function prepareEmptyDirectory(string $directory): void
    {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Invalid reference site output directory.');
        }

        if (is_dir($directory)) {
            $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
            if ($entries !== []) {
                throw new InvalidArgumentException('Reference site output directory must be empty.');
            }
            return;
        }

        if (file_exists($directory) || !mkdir($directory, 0775, true)) {
            throw new RuntimeException('Unable to create reference site output directory.');
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        $realSource = realpath($source);
        if ($realSource === false || !is_dir($realSource)) {
            throw new InvalidArgumentException('Reference source directory does not exist.');
        }
        if (!is_dir($target) && !mkdir($target, 0775, true)) {
            throw new RuntimeException('Unable to create reference target directory.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realSource, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $fileCount = 0;
        $totalBytes = 0;
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Reference site build refuses symbolic links.');
            }

            $relative = substr($item->getPathname(), strlen($realSource) + 1);
            if ($relative === false || $relative === '' || str_contains($relative, '..')) {
                throw new RuntimeException('Unsafe reference site path.');
            }

            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($destination) && !mkdir($destination, 0775, true)) {
                    throw new RuntimeException('Unable to create reference site directory.');
                }
                continue;
            }

            if (!$item->isFile()) {
                throw new RuntimeException('Unsupported reference site filesystem entry.');
            }

            $fileCount++;
            $size = $item->getSize();
            $totalBytes += $size;
            if ($fileCount > 5000 || $size > 10_000_000 || $totalBytes > 50_000_000) {
                throw new RuntimeException('Reference site source exceeds safety limits.');
            }
            if (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException('Unable to copy reference site asset.');
            }
        }
    }
}
