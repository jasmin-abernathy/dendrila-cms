<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

interface PublicPrivacyDocumentSource
{
    /** @return array{kind:string,html:string,approved_at:string}|null */
    public function find(string $siteId, string $kind): ?array;
}
