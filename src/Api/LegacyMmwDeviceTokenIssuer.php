<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use PDO;

final readonly class LegacyMmwDeviceTokenIssuer implements DeviceTokenIssuer
{
    public function __construct(
        private PDO $pdo,
        private string $siteId,
        private string $hmacSecret,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $siteId) !== 1 || strlen($hmacSecret) < 32) {
            throw new \InvalidArgumentException('Invalid legacy device-token configuration.');
        }
    }

    public function issue(AuthenticatedMember $member, string $deviceName): array
    {
        $deviceName = trim($deviceName);
        if ($member->siteId !== $this->siteId
            || $deviceName === '' || strlen($deviceName) > 120
            || preg_match('/[\x00-\x1f\x7f]/', $deviceName)) {
            throw new \InvalidArgumentException('Invalid device identity.');
        }

        $membership = $this->pdo->prepare(
            'SELECT 1 FROM memberships WHERE site_id = :site_id AND actor_id = :actor_id LIMIT 1'
        );
        $membership->execute(['site_id' => $member->siteId, 'actor_id' => $member->actorId]);
        if ($membership->fetchColumn() === false) {
            throw new AuthenticationFailure('Authentication failed.');
        }

        $deviceId = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $statement = $this->pdo->prepare(
            'INSERT INTO mmw_devices
             (device_id, actor_id, device_name, token_hash, created_at)
             VALUES (:device_id, :actor_id, :device_name, :token_hash, UTC_TIMESTAMP(6))'
        );
        $statement->execute([
            'device_id' => $deviceId,
            'actor_id' => $member->actorId,
            'device_name' => $deviceName,
            'token_hash' => hash_hmac('sha256', $token, $this->hmacSecret),
        ]);
        return ['device_token' => $token, 'expires_at' => null];
    }
}
