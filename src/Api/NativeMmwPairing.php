<?php
declare(strict_types=1);
namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Storage\PdoCmsStore;
use PDO;

/** Shared native Dendrila MMW association for every new CMS installation. */
final readonly class NativeMmwPairing implements IdentityProvider
{
    public function __construct(private PDO $pdo, private string $siteId, private string $key)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D',$siteId)
            || !preg_match('/^[a-f0-9]{64}$/D',$key)) {
            throw new \RuntimeException('MMW native pairing configuration is invalid.');
        }
    }

    public function start(string $username,string $password,string $deviceName): AuthenticationStartResult
    {
        throw new AuthenticationFailure('Pairing required.');
    }

    public function verify(string $challengeId,string $method,string $code): VerifiedLogin
    {
        throw new AuthenticationFailure('Pairing required.');
    }

    public function issueCode(ActorContext $actor): string
    {
        $this->assertOwner($actor);
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM native_mmw_pair_codes
            WHERE site_id=? AND actor_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE)');
        $q->execute([$this->siteId,$actor->actorId]);
        if ((int)$q->fetchColumn()>=5) throw new \DomainException('Trop de codes demandés. Réessayez dans dix minutes.');
        $code=(string)random_int(10000000,99999999);
        $q=$this->pdo->prepare('INSERT INTO native_mmw_pair_codes
            (site_id,code_hash,actor_id,created_at,expires_at)
            VALUES (?,?,?,UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE))');
        $q->execute([$this->siteId,$this->digest($code),$actor->actorId]);
        return $code;
    }

    public function pair(string $code): AuthenticatedMember
    {
        // Limit attempts even if the payload is malformed. Buckets survive PHP sessions.
        $this->limit('ip:'.($_SERVER['REMOTE_ADDR']??'unknown'),8);
        $this->limit('global',120);
        if (!preg_match('/^[0-9]{8}$/D',$code)) {
            throw new AuthenticationFailure('Authentication failed.');
        }
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT c.actor_id FROM native_mmw_pair_codes c
                INNER JOIN native_accounts a ON a.site_id=c.site_id AND a.actor_id=c.actor_id AND a.enabled=1
                INNER JOIN memberships m ON m.site_id=c.site_id AND m.actor_id=c.actor_id
                WHERE c.site_id=? AND c.code_hash=? AND c.consumed_at IS NULL
                  AND c.expires_at>UTC_TIMESTAMP(6) FOR UPDATE');
            $q->execute([$this->siteId,$this->digest($code)]);
            $actor=$q->fetchColumn();
            if(!is_string($actor)) {
                $this->pdo->rollBack();
                throw new AuthenticationFailure('Authentication failed.');
            }
            $q=$this->pdo->prepare('UPDATE native_mmw_pair_codes SET consumed_at=UTC_TIMESTAMP(6)
                WHERE site_id=? AND code_hash=? AND consumed_at IS NULL');
            $q->execute([$this->siteId,$this->digest($code)]);
            if($q->rowCount()!==1) {
                $this->pdo->rollBack();
                throw new AuthenticationFailure('Authentication failed.');
            }
            $this->pdo->commit();
            return new AuthenticatedMember($this->siteId,$actor);
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    public function devices(ActorContext $actor): array
    {
        $this->assertOwner($actor);
        $q=$this->pdo->prepare('SELECT device_id,device_name,created_at,last_seen_at FROM devices
            WHERE site_id=? AND actor_id=? AND revoked_at IS NULL ORDER BY created_at DESC LIMIT 100');
        $q->execute([$this->siteId,$actor->actorId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revoke(ActorContext $actor,string $deviceId): void
    {
        $this->assertOwner($actor);
        if (!preg_match('/^[a-f0-9]{32}$/D',$deviceId)) throw new \InvalidArgumentException('Invalid device identifier.');
        $q=$this->pdo->prepare('UPDATE devices SET revoked_at=UTC_TIMESTAMP(6)
            WHERE site_id=? AND actor_id=? AND device_id=? AND revoked_at IS NULL');
        $q->execute([$this->siteId,$actor->actorId,$deviceId]);
        if($q->rowCount()!==1) throw new \InvalidArgumentException('Device not found.');
    }

    private function assertOwner(ActorContext $actor): void
    {
        if($actor->siteId!==$this->siteId) throw new AccessDenied('Incorrect site.');
        $q=$this->pdo->prepare('SELECT 1 FROM native_accounts WHERE site_id=? AND actor_id=? AND enabled=1');
        $q->execute([$this->siteId,$actor->actorId]);
        if($q->fetchColumn()===false) throw new AccessDenied('Inactive account.');
        if(!(new PdoCmsStore($this->pdo))->actorHasPermission($this->siteId,$actor->actorId,'content.read'))
            throw new AccessDenied('Account cannot access site content.');
    }

    private function digest(string $code): string
    {
        return hash_hmac('sha256',$code,hex2bin($this->key));
    }

    private function limit(string $bucket,int $maximum): void
    {
        $hash=hash_hmac('sha256',$bucket,hex2bin($this->key));
        $now=time();
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('INSERT IGNORE INTO native_mmw_pair_limits
                (site_id,bucket,attempts,resets_at) VALUES (?,?,0,?)');
            $q->execute([$this->siteId,$hash,$now+900]);
            $q=$this->pdo->prepare('SELECT attempts,resets_at FROM native_mmw_pair_limits
                WHERE site_id=? AND bucket=? FOR UPDATE');
            $q->execute([$this->siteId,$hash]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new \RuntimeException('Rate limit bucket missing.');
            $fresh=(int)$row['resets_at']<=$now;
            $attempts=$fresh?1:(int)$row['attempts']+1;
            $reset=$fresh?$now+900:(int)$row['resets_at'];
            $this->pdo->prepare('UPDATE native_mmw_pair_limits SET attempts=?,resets_at=?
                WHERE site_id=? AND bucket=?')->execute([$attempts,$reset,$this->siteId,$hash]);
            $this->pdo->commit();
            if($attempts>$maximum)throw new AuthenticationFailure('Too many attempts.');
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }
}
