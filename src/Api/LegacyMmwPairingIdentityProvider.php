<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * Migration bridge for a site that already owns the historical MMW tables
 * site_admins, mmw_pair_codes and mmw_devices.
 *
 * The legacy site remains the authority for accounts and pairing codes.
 * Dendrila only maps an already verified legacy role to an existing Dendrila role.
 */
final class LegacyMmwPairingIdentityProvider implements IdentityProvider
{
    private const RATE_SCOPE = 'legacy_mmw_pairing';

    /** @param array<string,string> $roleMap */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $siteId,
        private readonly string $hmacSecret,
        private readonly array $roleMap,
        private readonly int $maxFailures = 10,
        private readonly int $windowSeconds = 60,
        private readonly int $blockSeconds = 60,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $siteId) !== 1
            || strlen($hmacSecret) < 32
            || $roleMap === []
            || $maxFailures < 1 || $maxFailures > 100
            || $windowSeconds < 10 || $windowSeconds > 3600
            || $blockSeconds < 10 || $blockSeconds > 3600) {
            throw new \InvalidArgumentException('Invalid legacy identity configuration.');
        }
        foreach ($roleMap as $source => $target) {
            if (!is_string($source) || !is_string($target)
                || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $source) !== 1
                || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $target) !== 1) {
                throw new \InvalidArgumentException('Invalid legacy role mapping.');
            }
        }
    }

    public function start(string $username, string $password, string $deviceName): AuthenticationStartResult
    {
        throw new AuthenticationFailure('Authentication failed.');
    }

    public function verify(string $challengeId, string $method, string $code): VerifiedLogin
    {
        throw new AuthenticationFailure('Authentication failed.');
    }

    public function pair(string $code): AuthenticatedMember
    {
        if (preg_match('/^[0-9]{8}$/D', $code) !== 1) {
            $this->slowFailure();
        }

        $this->pdo->beginTransaction();
        try {
            $rate = $this->lockedRateLimit();
            $now = self::now();
            if ($this->isBlocked($rate, $now)) {
                $this->pdo->rollBack();
                $this->slowFailure();
            }

            $statement = $this->pdo->prepare(
                'SELECT p.id, p.actor_id, a.role
                 FROM mmw_pair_codes p
                 INNER JOIN site_admins a ON a.id = p.actor_id
                 WHERE p.code_hash = :code_hash
                   AND p.consumed_at IS NULL
                   AND p.expires_at > UTC_TIMESTAMP(6)
                 LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['code_hash' => hash_hmac('sha256', $code, $this->hmacSecret)]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->recordFailure($rate, $now);
                $this->pdo->commit();
                $this->slowFailure();
            }

            $sourceRole = (string) ($row['role'] ?? '');
            $roleId = $this->roleMap[$sourceRole] ?? null;
            if (!is_string($roleId)) {
                throw new \RuntimeException('The verified legacy role is not mapped to Dendrila.');
            }
            $this->assertTargetRoleExists($roleId);

            $membership = $this->pdo->prepare(
                'INSERT INTO memberships (site_id, actor_id, role_id, created_at)
                 VALUES (:site_id, :actor_id, :role_id, UTC_TIMESTAMP(6))
                 ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)'
            );
            $membership->execute([
                'site_id' => $this->siteId,
                'actor_id' => (string) $row['actor_id'],
                'role_id' => $roleId,
            ]);

            $consume = $this->pdo->prepare(
                'UPDATE mmw_pair_codes
                 SET consumed_at = UTC_TIMESTAMP(6)
                 WHERE id = :id AND consumed_at IS NULL'
            );
            $consume->execute(['id' => $row['id']]);
            if ($consume->rowCount() !== 1) {
                throw new AuthenticationFailure('Authentication failed.');
            }

            $this->clearFailures();
            $this->pdo->commit();
            return new AuthenticatedMember($this->siteId, (string) $row['actor_id']);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function assertTargetRoleExists(string $roleId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM roles WHERE site_id = :site_id AND role_id = :role_id LIMIT 1'
        );
        $statement->execute(['site_id' => $this->siteId, 'role_id' => $roleId]);
        if ($statement->fetchColumn() === false) {
            throw new \RuntimeException('The mapped Dendrila role does not exist for this site.');
        }
    }

    /** @return array<string,mixed>|null */
    private function lockedRateLimit(): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT window_started_at, failures, blocked_until
             FROM auth_rate_limits
             WHERE site_id = :site_id AND scope = :scope
             LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['site_id' => $this->siteId, 'scope' => self::RATE_SCOPE]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed>|null $rate */
    private function isBlocked(?array $rate, DateTimeImmutable $now): bool
    {
        if (!is_string($rate['blocked_until'] ?? null) || $rate['blocked_until'] === '') {
            return false;
        }
        return new DateTimeImmutable($rate['blocked_until'], new DateTimeZone('UTC')) > $now;
    }

    /** @param array<string,mixed>|null $rate */
    private function recordFailure(?array $rate, DateTimeImmutable $now): void
    {
        $windowStarted = $rate !== null && is_string($rate['window_started_at'] ?? null)
            ? new DateTimeImmutable($rate['window_started_at'], new DateTimeZone('UTC'))
            : null;
        $insideWindow = $windowStarted !== null
            && $windowStarted > $now->modify('-' . $this->windowSeconds . ' seconds');
        $failures = $insideWindow ? ((int) ($rate['failures'] ?? 0) + 1) : 1;
        $window = $insideWindow ? $windowStarted : $now;
        $blockedUntil = $failures >= $this->maxFailures
            ? $now->modify('+' . $this->blockSeconds . ' seconds')
            : null;

        $statement = $this->pdo->prepare(
            'INSERT INTO auth_rate_limits
             (site_id, scope, window_started_at, failures, blocked_until, updated_at)
             VALUES (:site_id, :scope, :window_started_at, :failures, :blocked_until, UTC_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE
                 window_started_at = VALUES(window_started_at),
                 failures = VALUES(failures),
                 blocked_until = VALUES(blocked_until),
                 updated_at = UTC_TIMESTAMP(6)'
        );
        $statement->execute([
            'site_id' => $this->siteId,
            'scope' => self::RATE_SCOPE,
            'window_started_at' => $window->format('Y-m-d H:i:s.u'),
            'failures' => $failures,
            'blocked_until' => $blockedUntil?->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function clearFailures(): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM auth_rate_limits WHERE site_id = :site_id AND scope = :scope'
        );
        $statement->execute(['site_id' => $this->siteId, 'scope' => self::RATE_SCOPE]);
    }

    private function slowFailure(): never
    {
        usleep(250000);
        throw new AuthenticationFailure('Authentication failed.');
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
