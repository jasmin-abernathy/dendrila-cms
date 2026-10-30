<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Http\Request;
use PDO;

final readonly class LegacyMmwDeviceAuthenticator implements DeviceAuthenticator
{
    public function __construct(
        private PDO $pdo,
        private string $siteId,
        private string $hmacSecret,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $siteId) !== 1 || strlen($hmacSecret) < 32) {
            throw new \InvalidArgumentException('Invalid legacy device-authenticator configuration.');
        }
    }

    public function authenticate(Request $request): ActorContext
    {
        $token = $request->header('x-lepotager-device-token');
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw new Unauthorized('A valid device token is required.');
        }

        $statement = $this->pdo->prepare(
            'SELECT d.device_id, d.actor_id
             FROM mmw_devices d
             INNER JOIN memberships m
                 ON m.site_id = :site_id AND m.actor_id = d.actor_id
             WHERE d.token_hash = :token_hash
               AND d.revoked_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'site_id' => $this->siteId,
            'token_hash' => hash_hmac('sha256', $token, $this->hmacSecret),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new Unauthorized('Device token is invalid or revoked.');
        }

        $seen = $this->pdo->prepare(
            'UPDATE mmw_devices
             SET last_seen_at = UTC_TIMESTAMP(6)
             WHERE device_id = :device_id AND revoked_at IS NULL'
        );
        $seen->execute(['device_id' => $row['device_id']]);
        return new ActorContext($this->siteId, (string) $row['actor_id'], (string) $row['device_id'], 'mmw');
    }
}
