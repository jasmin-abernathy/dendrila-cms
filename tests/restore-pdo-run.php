<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Runtime\PublicSite;
use Dendrila\Cms\Media\MediaFactory;
function restored(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$root=getenv('RESTORE_PRIVATE_ROOT');
if(!$root||!getenv('RESTORE_DB_DSN'))throw new RuntimeException('Dedicated restore fixture required.');
$settings=require $root.'/config/local.php';
$settings['DB_DSN']=getenv('RESTORE_DB_DSN');$settings['MEDIA_PRIVATE_ROOT']=$root.'/storage/media';$settings['FORUM_PRIVATE_ROOT']=$root.'/storage/forum';
foreach($settings as $key=>$value)putenv($key.'='.$value);
$pdo=new PDO($settings['DB_DSN'],$settings['DB_USERNAME'],$settings['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
restored(str_contains((new PublicSite($pdo))->render('/presentation/'),'Le corps de la page'),'Published snapshot survives restore');
restored(!str_contains((new PublicSite($pdo))->render('/presentation/'),'Modifications non publiées'),'Unpublished changes stay private after restore');
restored(str_contains((new PublicSite($pdo))->render('/galerie/'),'media.php'),'Gallery survives restore');
$media=MediaFactory::fromEnvironment($pdo);$token=$pdo->query("SELECT public_token FROM media_variants WHERE kind='web' LIMIT 1")->fetchColumn();
restored(is_file($media->publicImage('default',$token)),'Restored photo bytes resolve from the new private root');
$row=$pdo->query("SELECT totp_cipher FROM native_accounts WHERE actor_id='owner'")->fetch();$raw=base64_decode($row['totp_cipher'],true);
$secret=sodium_crypto_secretbox_open(substr($raw,24),substr($raw,0,24),hex2bin($settings['DENDRILA_AUTH_KEY']));
$manifest=json_decode(file_get_contents(getenv('INSTALL_BACKUP_MANIFEST')),true,512,JSON_THROW_ON_ERROR);
restored(is_string($secret)&&hash('sha256',$secret)===$manifest['totp_hash'],'Restored authentication key decrypts the original second factor');
restored((int)$pdo->query("SELECT enabled FROM native_accounts WHERE email='member@example.test'")->fetchColumn()===0,'Revocation survives restore');
echo "Backup restored: published pages, private drafts, gallery files, authentication key and revoked accounts.\n";
