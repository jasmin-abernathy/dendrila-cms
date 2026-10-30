<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;
use Dendrila\Cms\Auth\NativeIdentity;
use Dendrila\Cms\Auth\Totp;
use Dendrila\Cms\Authorization\{ActorContext,AuthorizationService};
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Navigation\{NavigationService,PdoNavigationRepository};
use PDO;
final class Installer
{
    public static function connect(array $db):PDO {
        foreach(['host','name','user','password','port'] as $key)if(!is_string($db[$key]??null))throw new \InvalidArgumentException('Configuration de base incomplète.');
        if(!preg_match('/^[a-zA-Z0-9.:-]{1,253}$/D',$db['host'])||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$db['name'])||!ctype_digit($db['port'])||(int)$db['port']<1||(int)$db['port']>65535||strlen($db['password'])>1024)throw new \InvalidArgumentException('Adresse de base invalide.');
        $pdo=new PDO(self::dsn($db),$db['user'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        if($pdo->query('SHOW TABLES')->fetchColumn()!==false)throw new \DomainException('Choisissez une base vide réservée à cette nouvelle installation. Les bases des sites existants ne sont jamais écrasées.');
        return $pdo;
    }
    public static function dsn(array $db):string{return 'mysql:host='.$db['host'].';port='.$db['port'].';dbname='.$db['name'].';charset=utf8mb4';}
    public static function install(array $state,array $account,string $directory):void {
        if(!isset(Catalog::themes()[$state['theme']??'']))throw new \InvalidArgumentException('Thème inconnu.');
        \Dendrila\Cms\Runtime\Presentation::forTheme($state['theme']);
        $addons=Catalog::selection($state['addons']??[]);
        foreach(Catalog::requirements($addons)['extensions'] as $ext)if(!extension_loaded($ext))throw new \RuntimeException('Extension PHP manquante : '.$ext);
        $name=trim($account['name']??'');$email=strtolower(trim($account['email']??''));$password=$account['password']??'';
        if($name===''||mb_strlen($name)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||strlen($password)<12||strlen($password)>72)throw new \InvalidArgumentException('Nom, e-mail ou mot de passe invalide (12 caractères minimum, 72 octets maximum).');
        $url=rtrim($account['url']??'','/');
        if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_USER)!==null||parse_url($url,PHP_URL_QUERY)!==null||parse_url($url,PHP_URL_FRAGMENT)!==null||!in_array(parse_url($url,PHP_URL_PATH),[null,''],true))throw new \InvalidArgumentException('Indiquez l’origine HTTPS du site, sans chemin.');
        $step=Totp::verify($state['secret']??'',$account['code']??'');if($step===null)throw new \InvalidArgumentException('Le code de double authentification est incorrect.');
        // Validate packaged seed resources before connecting or creating any tables.
        $seedRoot=dirname(__DIR__,2);
        foreach(['/database/seeders/default-roles.json','/examples/content-types/page.json'] as $resource){
            if(!is_readable($seedRoot.$resource))throw new \RuntimeException('Required installation resource is missing.');
        }
        $roles=json_decode((string)file_get_contents($seedRoot.'/database/seeders/default-roles.json'),true,512,JSON_THROW_ON_ERROR);
        $type=(string)file_get_contents($seedRoot.'/examples/content-types/page.json');
        $typeData=json_decode($type,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($roles)||$roles===[]||!is_array($typeData)||($typeData['id']??null)!=='page')throw new \RuntimeException('Invalid installation resources.');
        $lock=fopen($directory.'/install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new \RuntimeException('Installation déjà en cours.');
        try{
            if(is_file($directory.'/local.php'))throw new \DomainException('Le site est déjà installé.');
            $pdo=self::connect($state['db']);
            $private=dirname($directory).'/storage';
            if(!is_dir($private)&&!mkdir($private,0700,true))throw new \RuntimeException('Stockage privé inaccessible.');
            foreach(['media','forum'] as $module)if(in_array($module,$addons,true)&&!is_dir($private.'/'.$module)&&!mkdir($private.'/'.$module,0700,true))throw new \RuntimeException('Stockage du module inaccessible.');
            $settings=['APP_ENV'=>'production','APP_BASE_URL'=>$url,'DB_DSN'=>self::dsn($state['db']),'DB_USERNAME'=>$state['db']['user'],'DB_PASSWORD'=>$state['db']['password'],'DENDRILA_SITE_ID'=>'default','DENDRILA_SITE_NAME'=>$name,'DENDRILA_THEME'=>$state['theme'],'DENDRILA_ADDONS'=>implode(',',$addons),'DENDRILA_AUTH_KEY'=>bin2hex(random_bytes(32))];
            if(in_array('media',$addons,true))$settings+=['MEDIA_PRIVATE_ROOT'=>$private.'/media','MEDIA_PUBLIC_ENDPOINT'=>$url.'/media.php'];
            if(in_array('forum',$addons,true))$settings['FORUM_PRIVATE_ROOT']=$private.'/forum';
            // Verify atomic private config write before creating any tables.
            $tmp=$directory.'/install-'.bin2hex(random_bytes(8)).'.tmp';
            $old=umask(0077);$written=file_put_contents($tmp,"<?php\nreturn ".var_export($settings,true).";\n",LOCK_EX);umask($old);
            if($written===false)throw new \RuntimeException('Configuration non inscriptible.');
            try {
                (new Migrator($pdo))->run();
                $pdo->beginTransaction();
                $pdo->exec("INSERT INTO sites VALUES ('default',".$pdo->quote($name).",1,1,'{\"review_before_publish\":true,\"allow_offline_queue\":false}',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
                $modules=in_array('media',$addons,true)?[\Dendrila\Cms\Api\MediaLibraryModule::definition()]:[];
                $q=$pdo->prepare("INSERT INTO site_api_settings (site_id,branding_json,modules_json,created_at,updated_at) VALUES ('default','{}',?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");$q->execute([json_encode($modules,JSON_THROW_ON_ERROR)]);
                foreach($roles as $role){$q=$pdo->prepare("INSERT INTO roles VALUES ('default',?,?)");$q->execute([$role['id'],$role['label']]);foreach($role['permissions'] as $permission){$q=$pdo->prepare("INSERT INTO role_permissions VALUES ('default',?,?)");$q->execute([$role['id'],$permission]);}}
                $pdo->exec("INSERT INTO memberships VALUES ('default','owner','owner',UTC_TIMESTAMP(6))");
                $q=$pdo->prepare("INSERT INTO content_types VALUES ('default','page',?,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");$q->execute([$type]);
                $q=$pdo->prepare("INSERT INTO privacy_settings (site_id,settings_json,created_at,updated_at) VALUES ('default',?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");$q->execute([json_encode(\Dendrila\Cms\Privacy\PrivacyService::defaultSettings(),JSON_THROW_ON_ERROR)]);
                $identity=new NativeIdentity($pdo,'default',$settings['DENDRILA_AUTH_KEY']);
                $q=$pdo->prepare("INSERT INTO native_accounts (site_id,actor_id,email,password_hash,totp_cipher,last_totp_step,created_at) VALUES ('default','owner',?,?,?,?,UTC_TIMESTAMP(6))");$q->execute([$email,password_hash($password,PASSWORD_DEFAULT),$identity->encrypt($state['secret']),$step]);
                $pdo->commit();
                $store=new PdoCmsStore($pdo);$actor=new ActorContext('default','owner');$authz=new AuthorizationService($store);$content=new ContentService($store,$authz);
                $home=$content->createDraft($actor,'page',['title'=>$name,'slug'=>'home','blocks'=>[['type'=>'text','text'=>'Bienvenue sur notre site.']]],'install-home');
                $content->transition($actor,$home['content']['id'],'published',1,'install-publish-home');
                $items=[['id'=>'home','label'=>'Accueil','url'=>'/','visible'=>true]];
                if(in_array('forum',$addons,true))$items[]=['id'=>'forum','label'=>'Forum','url'=>'/forum/','visible'=>true];
                (new NavigationService(new PdoNavigationRepository($pdo),$store,$authz))->save($actor,'main','Menu principal','primary',$items,0);
                if(!rename($tmp,$directory.'/local.php'))throw new \RuntimeException('Finalisation de la configuration impossible.');
                @unlink($directory.'/install-token.json');
            }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();@unlink($tmp);throw $e;}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
