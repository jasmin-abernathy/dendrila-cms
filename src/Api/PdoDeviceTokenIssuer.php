<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use PDO;

final readonly class PdoDeviceTokenIssuer implements DeviceTokenIssuer
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(AuthenticatedMember $member, string $deviceName): array
    {
        $deviceName = trim($deviceName);
        if ($deviceName === '' || strlen($deviceName) > 120 || preg_match('/[\x00-\x1f\x7f]/', $deviceName)) {
            throw new \InvalidArgumentException('Device name must contain 1–120 printable characters.');
        }
        $membership = $this->pdo->prepare(
            'SELECT 1 FROM memberships WHERE site_id = :site_id AND actor_id = :actor_id LIMIT 1'
        );
        $membership->execute(['site_id' => $member->siteId, 'actor_id' => $member->actorId]);
        if ($membership->fetchColumn() === false) {
            throw new AuthenticationFailure('The verified identity is not a member of the requested site.');
        }

        $deviceId = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $insert = $this->pdo->prepare(
            'INSERT INTO devices (site_id, device_id, actor_id, device_name, token_hash, created_at)
             VALUES (:site_id, :device_id, :actor_id, :device_name, :token_hash, UTC_TIMESTAMP(6))'
        );
        $insert->execute([
            'site_id' => $member->siteId,
            'device_id' => $deviceId,
            'actor_id' => $member->actorId,
            'device_name' => $deviceName,
            'token_hash' => hash('sha256', $token),
        ]);
        return ['device_token' => $token, 'expires_at' => null];
    }
}
