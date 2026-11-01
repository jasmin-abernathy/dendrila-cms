<?php
declare(strict_types=1);
namespace Dendrila\Cms\Front;

/**
 * Small native contact transport; no third-party embeds or external spam calls.
 * Sessions are used only on contact pages. Rate data contains timestamps, not
 * visitor addresses or message bodies, and lives outside the web document root.
 */
final class PublicContactForm
{
    private const SESSION = 'dendrila_contact';
    public static function recipient(): ?string
    {
        $email=getenv('DENDRILA_CONTACT_EMAIL') ?: '';
        return is_string($email) && strlen($email)<=254 && filter_var($email,FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    private static function senderAddress(): ?string
    {
        $origin=getenv('APP_BASE_URL') ?: '';
        $host=parse_url($origin,PHP_URL_HOST);
        if (!is_string($host) || !preg_match('/^[a-z0-9.-]{3,180}$/iD',$host)
            || !str_contains($host,'.')) return null;
        return 'no-reply@'.$host;
    }
    private static function session(): void
    {
        if (session_status()===PHP_SESSION_ACTIVE) return;
        if (headers_sent()) throw new \RuntimeException('Contact session headers already sent.');
        session_name('dendrila_contact_sid');
        session_set_cookie_params([
            'lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off',
            'httponly'=>true,'samesite'=>'Lax'
        ]);
        session_start();
    }
    /** @return array<string,mixed> */
    public static function context(): array
    {
        if(self::recipient()===null || self::senderAddress()===null)
            return ['enabled'=>false,'preview'=>false,'token'=>'','notice'=>''];
        self::session();
        $state=$_SESSION[self::SESSION]??null;
        if(!is_array($state)||!is_string($state['token']??null)||strlen($state['token'])!==64){
            $state=['token'=>bin2hex(random_bytes(32)),'created'=>time(),'sent'=>[]];
            $_SESSION[self::SESSION]=$state;
        }
        $notice=$_SESSION['dendrila_contact_flash']??'';
        unset($_SESSION['dendrila_contact_flash']);
        return [
            'enabled'=>self::recipient()!==null,
            'preview'=>false,
            'token'=>$state['token'],
            'notice'=>is_string($notice)?$notice:'',
        ];
    }
    public static function flash(string $notice): void
    {
        self::session();
        $_SESSION['dendrila_contact_flash']=mb_substr($notice,0,500,'UTF-8');
    }

    public static function submit(array $values, string $ip, ?callable $sender=null): string
    {
        self::session();
        $recipient=self::recipient();
        $from=self::senderAddress();
        if($recipient===null || $from===null) return 'Le formulaire n’est pas encore configuré.';
        $state=$_SESSION[self::SESSION]??null;
        if(!is_array($state)||!is_string($state['token']??null)
            ||!is_string($values['csrf']??null)
            ||!hash_equals($state['token'],$values['csrf'])) return 'La session a expiré. Rechargez la page.';
        $now=time();
        $created=(int)($state['created']??0);
        if($created>$now||$created<$now-3600||$now-$created<2)
            return 'Veuillez patienter quelques secondes avant de valider le formulaire.';
        foreach(['name','email','message','website'] as $key){
            if(!is_string($values[$key]??null))return 'Veuillez vérifier les champs du formulaire.';
        }
        $name=trim($values['name']);$email=trim($values['email']);$message=trim($values['message']);
        $topic=is_string($values['subject']??null)?trim($values['subject']):'';
        if(strlen($topic)>140 || preg_match('/[\r\n\x00-\x1f]/',$topic))
            return 'Veuillez vérifier l’objet de votre message.';
        if(strlen($name)>120||strlen($email)>254||strlen($message)>5000
            ||strlen($values['website'])>200||strlen($name)<2||strlen($message)<10
            ||!filter_var($email,FILTER_VALIDATE_EMAIL)
            ||preg_match('/[\r\n\x00-\x1f]/',$name.$email)) return 'Veuillez vérifier le nom, l’e-mail et le message.';
        // Change the token after a valid attempt to prevent replay.
        $_SESSION[self::SESSION]['token']=bin2hex(random_bytes(32));
        $_SESSION[self::SESSION]['created']=$now;
        if(trim($values['website'])!=='') return 'Votre demande a été prise en compte.';
        if(!self::throttle($ip,$now))return 'Trop de demandes ont été reçues. Réessayez plus tard.';
        $subject='Message reçu depuis le formulaire de contact';
        $body="Nom : ".$name."\nAdresse e-mail : ".$email."\nObjet : ".$topic."\n\nMessage :\n".$message."\n";
        $headers=['From: '.$from,'Reply-To: '.$email,'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8','X-Auto-Response-Suppress: All'];
        $send=$sender??static fn(string $to,string $sub,string $text,array $headers):bool=>mail($to,$sub,$text,implode("\r\n",$headers));
        try{$accepted=(bool)$send($recipient,$subject,$body,$headers);}
        catch(\Throwable $e){error_log('Dendrila contact transport failed: '.$e::class);$accepted=false;}
        if(!$accepted)return 'L’envoi n’a pas abouti. Réessayez plus tard.';
        return 'Votre message a bien été transmis.';
    }
    private static function throttle(string $ip,int $now):bool
    {
        $root=getenv('DENDRILA_CONTACT_THROTTLE_DIR') ?: sys_get_temp_dir().'/dendrila-contact-rate';
        if(!is_dir($root) && !@mkdir($root,0700,true) && !is_dir($root))return false;
        $key=getenv('DENDRILA_AUTH_KEY') ?: 'site';
        $name=hash_hmac('sha256',($ip?:'unknown').'|'.(getenv('DENDRILA_SITE_ID')?:'default'),$key);
        $fp=@fopen($root.'/'.$name,'c+');
        if(!$fp)return false;
        try{
            if(!flock($fp,LOCK_EX))return false;
            $data=stream_get_contents($fp);
            $history=is_string($data)?json_decode($data,true):[];
            if(!is_array($history))$history=[];
            $history=array_values(array_filter($history,
                static fn(mixed $n):bool=>is_int($n) && $n>$now-3600 && $n<=$now));
            if(count($history)>=3)return false;
            $history[]=$now;
            ftruncate($fp,0);rewind($fp);
            fwrite($fp,json_encode($history,JSON_THROW_ON_ERROR));
            fflush($fp);return true;
        }finally{flock($fp,LOCK_UN);fclose($fp);}
    }
}
