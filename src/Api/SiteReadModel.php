<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

interface SiteReadModel
{
    /** @return array<string, mixed> */
    public function configuration(string $siteId): array;

    /** @return array<string, mixed> */
    public function snapshot(string $siteId): array;
}
