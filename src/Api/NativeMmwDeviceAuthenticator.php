<?php
declare(strict_types=1);
namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Http\Request;
use PDO;

/** Revoked devices and disabled native accounts cannot continue editing content. */
final readonly class NativeMmwDeviceAuthenticator implements DeviceAuthenticator
{
    public function __construct(private PDO $pdo,private string $siteId){}
    public function authenticate(Request $request): ActorContext
    {
        $actor=(new PdoDeviceAuthenticator($this->pdo))->authenticate($request);
        if ($actor->siteId!==$this->siteId) throw new Unauthorized('Device site mismatch.');
        $q=$this->pdo->prepare('SELECT 1 FROM native_accounts WHERE site_id=? AND actor_id=? AND enabled=1');
        $q->execute([$this->siteId,$actor->actorId]);
        if ($q->fetchColumn()===false) throw new Unauthorized('The account is disabled.');
        return $actor;
    }
}
