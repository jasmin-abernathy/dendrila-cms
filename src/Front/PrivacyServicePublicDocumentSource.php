<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use Dendrila\Cms\Privacy\PrivacyService;

final readonly class PrivacyServicePublicDocumentSource implements PublicPrivacyDocumentSource
{
    public function __construct(private PrivacyService $privacy) {}

    public function find(string $siteId, string $kind): ?array
    {
        return $this->privacy->publicLegalDocument($siteId, $kind);
    }
}
