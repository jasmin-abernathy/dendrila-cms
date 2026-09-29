<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

interface DeviceTokenIssuer
{
    /** @return array{device_token: string, expires_at: null} */
    public function issue(AuthenticatedMember $member, string $deviceName): array;
}
