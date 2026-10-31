<?php
declare(strict_types=1);
namespace Dendrila\Cms\Auth;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\BackOffice\IdentityProvider;
use PDO;

final readonly class NativeIdentity implements IdentityProvider
{
    public function __construct(private PDO $pdo, private string $siteId, private string $key) {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new \RuntimeException('Native authentication key missing.');
    }
    public function encrypt(string $secret): string {
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce.sodium_crypto_secretbox($secret,$nonce,hex2bin($this->key)));
    }
    private function decrypt(string $cipher): string {
        $raw=base64_decode($cipher,true);
        if($raw===false || strlen($raw)<40)throw new \RuntimeException('Invalid authentication secret.');
        $secret=sodium_crypto_secretbox_open(substr($raw,24),substr($raw,0,24),hex2bin($this->key));
        if($secret===false)throw new \RuntimeException('Cannot decrypt authentication secret.');
        return $secret;
    }
    public function currentActor(): ?ActorContext {
        $session=$_SESSION['dendrila_identity']??null;
        if(!is_array($session) || ($session['site']??null)!==$this->siteId || ($session['expires']??0)<time() || ($session['idle']??0)<time())return null;
        $q=$this->pdo->prepare('SELECT session_version FROM native_accounts WHERE site_id=? AND actor_id=? AND enabled=1');
        $q->execute([$this->siteId,$session['actor']]);
        $version=$q->fetchColumn();
        if($version===false || (int)$version!==$session['version'])return null;
        $_SESSION['dendrila_identity']['idle']=time()+1800;
        return new ActorContext($this->siteId,$session['actor']);
    }
    public function login(string $email,string $password,string $code,string $ip): bool {
        if(strlen($email)>254 || strlen($password)>1024 || strlen($code)>6)return false;
        $email=strtolower(trim($email));
        // Shared persistent limits: new sessions cannot reset either bucket.
        foreach(['email:'.$email=>'8','ip:'.$ip=>'40'] as $bucket=>$maximum) {
            if(!$this->attempt($bucket,(int)$maximum))return false;
        }
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare('SELECT * FROM native_accounts WHERE site_id=? AND email=? AND enabled=1 FOR UPDATE');
            $q->execute([$this->siteId,$email]);$user=$q->fetch(PDO::FETCH_ASSOC);
            $valid=password_verify($password,is_array($user)?$user['password_hash']:'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            $step=$valid&&is_array($user)?Totp::verify($this->decrypt($user['totp_cipher']),$code,(int)$user['last_totp_step']):null;
            if($step===null){$this->pdo->rollBack();return false;}
            $q=$this->pdo->prepare('UPDATE native_accounts SET last_totp_step=? WHERE site_id=? AND actor_id=?');
            $q->execute([$step,$this->siteId,$user['actor_id']]);$this->pdo->commit();
            session_regenerate_id(true);
            unset($_SESSION['dendrila_backoffice_csrf']);
            $_SESSION['dendrila_identity']=['site'=>$this->siteId,'actor'=>$user['actor_id'],'version'=>(int)$user['session_version'],'expires'=>time()+28800,'idle'=>time()+1800];
            return true;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function attempt(string $value,int $maximum): bool {
        $this->pdo->beginTransaction();
        try {
            $bucket=hash_hmac('sha256',$value,$this->key);$now=time();
            $q=$this->pdo->prepare('INSERT IGNORE INTO native_login_limits VALUES (?,?,0,?)');$q->execute([$this->siteId,$bucket,$now+900]);
            $q=$this->pdo->prepare('SELECT attempts,resets_at FROM native_login_limits WHERE site_id=? AND bucket=? FOR UPDATE');$q->execute([$this->siteId,$bucket]);$row=$q->fetch(PDO::FETCH_ASSOC);
            $attempts=(int)$row['resets_at']<=$now?1:(int)$row['attempts']+1;
            $reset=(int)$row['resets_at']<=$now?$now+900:(int)$row['resets_at'];
            $q=$this->pdo->prepare('UPDATE native_login_limits SET attempts=?,resets_at=? WHERE site_id=? AND bucket=?');$q->execute([$attempts,$reset,$this->siteId,$bucket]);$this->pdo->commit();
            return $attempts<=$maximum;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
