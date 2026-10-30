<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;
use PDO;
use Dendrila\Cms\Auth\NativeIdentity;
final class Application
{
    public static function database(): PDO {
        return new PDO(getenv('DB_DSN')?:'',getenv('DB_USERNAME')?:'',getenv('DB_PASSWORD')?:'',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    public static function session(): void {
        $secure=isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
        $local=getenv('APP_ENV')==='local' && in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
        if(!$secure&&!$local){http_response_code(403);exit('Une connexion HTTPS est nécessaire.');}
        if(session_status()!==PHP_SESSION_ACTIVE){
            ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
            session_name('dendrila_session');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);session_start();
        }
        header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
    }
    public static function identity(PDO $pdo): NativeIdentity {return new NativeIdentity($pdo,self::site(),getenv('DENDRILA_AUTH_KEY')?:'');}
    public static function site(): string {return getenv('DENDRILA_SITE_ID')?:throw new \RuntimeException('Site is not installed.');}
    public static function addons(): array {return array_filter(explode(',',getenv('DENDRILA_ADDONS')?:''));}
    public static function page(string $title,string $body): string {
        return '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.self::h($title).' — Dendrila</title><link rel="stylesheet" href="/backoffice-assets/backoffice.css"><link rel="stylesheet" href="/assets/install.css"><body><main class="setup"><a href="/">Voir le site</a><h1>'.self::h($title).'</h1>'.$body.'</main></body></html>';
    }
    public static function h(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
}
