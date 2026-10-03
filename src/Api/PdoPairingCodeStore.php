<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

use DateTimeImmutable;
use PDO;
use Throwable;

final readonly class PdoPairingCodeStore implements PairingCodeStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(string $siteId, string $actorId, string $code, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO device_pairing_codes (code_hash, site_id, actor_id, expires_at, created_at)
             VALUES (:code_hash, :site_id, :actor_id, :expires_at, UTC_TIMESTAMP(6))'
        );
        $statement->execute([
            'code_hash' => hash('sha256', $code),
            'site_id' => $siteId,
            'actor_id' => $actorId,
            'expires_at' => $expiresAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function consumeHash(string $codeHash): ?AuthenticatedMember
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $codeHash)) {
            return null;
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $select = $this->pdo->prepare(
                'SELECT site_id, actor_id
                 FROM device_pairing_codes
                 WHERE code_hash = :code_hash
                   AND consumed_at IS NULL
                   AND expires_at > UTC_TIMESTAMP(6)
                 FOR UPDATE'
            );
            $select->execute(['code_hash' => $codeHash]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return null;
            }

            $consume = $this->pdo->prepare(
                'UPDATE device_pairing_codes
                 SET consumed_at = UTC_TIMESTAMP(6)
                 WHERE code_hash = :code_hash
                   AND consumed_at IS NULL
                   AND expires_at > UTC_TIMESTAMP(6)'
            );
            $consume->execute(['code_hash' => $codeHash]);
            if ($consume->rowCount() !== 1) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return null;
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return new AuthenticatedMember((string) $row['site_id'], (string) $row['actor_id']);
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
