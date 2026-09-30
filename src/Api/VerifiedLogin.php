<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

final readonly class VerifiedLogin
{
    public function __construct(public AuthenticatedMember $member, public string $deviceName)
    {
        if (trim($deviceName) === '' || strlen($deviceName) > 120 || preg_match('/[\x00-\x1f\x7f]/', $deviceName)) {
            throw new \InvalidArgumentException('Device name must contain 1–120 printable characters.');
        }
    }
}
