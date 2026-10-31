<?php
declare(strict_types=1);
namespace Dendrila\Cms\Auth;
use PDO;
use Dendrila\Cms\Authorization\{ActorContext,AuthorizationService};
use Dendrila\Cms\Storage\PdoCmsStore;
final readonly class Accounts
{
    public function __construct(private PDO $pdo,private string $siteId,private string $key){}
    public function invite(ActorContext $actor,string $email,string $role):string {
        if($actor->siteId!==$this->siteId)throw new \InvalidArgumentException('Site mismatch.');
        (new AuthorizationService(new PdoCmsStore($this->pdo)))->require($actor,'users.manage');
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||!in_array($role,['editor','contributor','viewer','member'],true))throw new \InvalidArgumentException('Invitation invalide.');
        $token=bin2hex(random_bytes(32));$q=$this->pdo->prepare('INSERT INTO native_invitations VALUES (?,?,?,?,?,?)');
        $q->execute([$this->siteId,hash('sha256',$token),$email,$role,time()+172800,$actor->actorId]);return $token;
    }
    public function redeem(string $token,string $password,string $secret,string $code):void {
        if(strlen($password)<12||strlen($password)>72||!preg_match('/^[a-f0-9]{64}$/D',$token))throw new \InvalidArgumentException('Invitation ou mot de passe invalide.');
        $step=Totp::verify($secret,$code);if($step===null)throw new \InvalidArgumentException('Code de double authentification incorrect.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT * FROM native_invitations WHERE site_id=? AND token_hash=? AND expires_at>? FOR UPDATE');$q->execute([$this->siteId,hash('sha256',$token),time()]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new \InvalidArgumentException('Invitation expirée ou déjà utilisée.');
            $id='user_'.bin2hex(random_bytes(12));
            $q=$this->pdo->prepare('INSERT INTO memberships VALUES (?,?,?,UTC_TIMESTAMP(6))');$q->execute([$this->siteId,$id,$row['role_id']]);
            $q=$this->pdo->prepare('INSERT INTO native_accounts (site_id,actor_id,email,password_hash,totp_cipher,last_totp_step,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(6))');
            $q->execute([$this->siteId,$id,$row['email'],password_hash($password,PASSWORD_DEFAULT),(new NativeIdentity($this->pdo,$this->siteId,$this->key))->encrypt($secret),$step]);
            $q=$this->pdo->prepare('DELETE FROM native_invitations WHERE site_id=? AND token_hash=?');$q->execute([$this->siteId,hash('sha256',$token)]);$this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function disable(ActorContext $actor,string $target):void {
        if($actor->siteId!==$this->siteId||$target===$actor->actorId)throw new \InvalidArgumentException('Action refusée.');
        (new AuthorizationService(new PdoCmsStore($this->pdo)))->require($actor,'users.manage');
        $q=$this->pdo->prepare("UPDATE native_accounts a JOIN memberships m ON a.site_id=m.site_id AND a.actor_id=m.actor_id SET a.enabled=0,a.session_version=a.session_version+1 WHERE a.site_id=? AND a.actor_id=? AND m.role_id NOT IN ('owner','admin')");$q->execute([$this->siteId,$target]);
        if($q->rowCount()!==1)throw new \InvalidArgumentException('Compte introuvable ou protégé.');
    }
}
