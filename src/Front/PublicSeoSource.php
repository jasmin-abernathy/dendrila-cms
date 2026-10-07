<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

interface PublicSeoSource
{
    /** @return array<string,mixed> */
    public function metadata(string $siteId, string $contentId): array;
}
