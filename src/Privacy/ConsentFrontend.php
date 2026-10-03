<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

final readonly class ConsentFrontend
{
    public function __construct(
        private PrivacyService $privacy,
        private ConsentRenderer $renderer = new ConsentRenderer(),
    ) {
    }

    public function render(string $siteId, string $assetBaseUrl = '/assets', string $locale = 'fr'): string
    {
        return $this->renderer->render(
            $this->privacy->consentManifest($siteId),
            $assetBaseUrl,
            $locale,
        );
    }
}
