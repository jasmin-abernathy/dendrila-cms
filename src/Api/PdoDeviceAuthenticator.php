<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Http\Request;
use PDO;

final readonly class PdoDeviceAuthenticator implements DeviceAuthenticator
{
    public function __construct(private PDO $pdo)
    {
    }

    public function authenticate(Request $request): ActorContext
    {
        $token = $request->header('x-lepotager-device-token');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new Unauthorized('A valid device token is required.');
        }
        $statement = $this->pdo->prepare(
            'SELECT d.site_id, d.actor_id, d.device_id
             FROM devices d
             INNER JOIN memberships m ON m.site_id = d.site_id AND m.actor_id = d.actor_id
             WHERE d.token_hash = :token_hash AND d.revoked_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new Unauthorized('Device token is invalid or revoked.');
        }
        $seen = $this->pdo->prepare(
            'UPDATE devices SET last_seen_at = UTC_TIMESTAMP(6)
             WHERE site_id = :site_id AND device_id = :device_id AND revoked_at IS NULL'
        );
        $seen->execute(['site_id' => $row['site_id'], 'device_id' => $row['device_id']]);
        return new ActorContext($row['site_id'], $row['actor_id'], $row['device_id'], 'mmw');
    }
}
