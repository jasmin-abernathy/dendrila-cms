<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Installation\{Installer,Migrator};
use Dendrila\Cms\Auth\{Totp,NativeIdentity,Accounts};
use Dendrila\Cms\Authorization\{ActorContext,AuthorizationService,AccessDenied};
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\BackOffice\CoreData;
use Dendrila\Cms\Navigation\{NavigationService,PdoNavigationRepository};
use Dendrila\Cms\Runtime\PublicSite;
use Dendrila\Cms\Media\MediaFactory;
function verifyInstall(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
if(!getenv('INSTALL_DB_NAME')){echo "Installation PDO tests skipped: INSTALL_DB_NAME missing.\n";exit;}
session_start();
$base=sys_get_temp_dir().'/dendrila-install-'.bin2hex(random_bytes(6));mkdir($base.'/config',0700,true);
$db=['host'=>getenv('INSTALL_DB_HOST')?:'127.0.0.1','port'=>getenv('INSTALL_DB_PORT')?:'3306','name'=>getenv('INSTALL_DB_NAME'),'user'=>getenv('DB_USERNAME'),'password'=>getenv('DB_PASSWORD')?:''];
$secret=Totp::secret();$password='Test-only-password-2026!';
$setupCode=Totp::code($secret,intdiv(time(),30));
Installer::install(['theme'=>'quiet-elegant','addons'=>['media','forum'],'secret'=>$secret,'db'=>$db],['name'=>'Installation Test','email'=>'owner@example.test','password'=>$password,'url'=>'https://example.test','code'=>$setupCode],$base.'/config');
$settings=require $base.'/config/local.php';foreach($settings as $k=>$v)putenv($k.'='.$v);
$pdo=new PDO($settings['DB_DSN'],$settings['DB_USERNAME'],$settings['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
verifyInstall((new Migrator($pdo))->run()===[],'Migrations repeat safely');
try{Installer::connect($db);throw new RuntimeException('Nonempty DB accepted');}catch(DomainException){}
verifyInstall(str_contains((new PublicSite($pdo))->render('/'),'Bienvenue sur notre site.'),'Fresh site serves a published homepage');

$appearance=\Dendrila\Cms\Runtime\AppearanceSettings::read($pdo,'default');
verifyInstall($appearance['theme']==='quiet-elegant'&&$appearance['brand']===[],'Initial appearance follows installed theme');
$initialVersion=(int)$pdo->query("SELECT config_version FROM sites WHERE site_id='default'")->fetchColumn();
$brand=['primary'=>'#9d2941','text'=>'#191718','background'=>'#ffffff','logo_url'=>'https://example.test/assets/logo.svg','heading_font'=>'editorial-serif'];
\Dendrila\Cms\Runtime\AppearanceSettings::save($pdo,'default',hash('sha256',$appearance['raw']),'editorial',$brand);
$changed=\Dendrila\Cms\Runtime\AppearanceSettings::read($pdo,'default');
verifyInstall($changed['theme']==='editorial'&&$changed['brand']===$brand,'Saved site appearance survives DB read');
verifyInstall((int)$pdo->query("SELECT config_version FROM sites WHERE site_id='default'")->fetchColumn()===$initialVersion+1,'Appearance update invalidates client configuration');
$html=(new PublicSite($pdo))->render('/');
verifyInstall(str_contains($html,'data-dendrila-theme="editorial"')&&str_contains($html,'#9d2941'),'Published page uses saved design kit and colors');
try{
    \Dendrila\Cms\Runtime\AppearanceSettings::save($pdo,'default',hash('sha256',$appearance['raw']),'quiet-elegant',[]);
    throw new RuntimeException('Stale appearance form accepted');
}catch(DomainException){}
try{
    \Dendrila\Cms\Runtime\AppearanceSettings::validate('editorial',['text'=>'#ffffff','background'=>'#ffffff']);
    throw new RuntimeException('Unsafe low contrast accepted');
}catch(InvalidArgumentException){}
try{
    \Dendrila\Cms\Runtime\AppearanceSettings::validate('editorial',['logo_url'=>'http://insecure.test/logo.svg']);
    throw new RuntimeException('Insecure logo accepted');
}catch(InvalidArgumentException){}
try{
    \Dendrila\Cms\Runtime\AppearanceSettings::validate('editorial',['primary'=>'red; } body{display:none']);
    throw new RuntimeException('CSS injection accepted');
}catch(InvalidArgumentException){}
\Dendrila\Cms\Runtime\AppearanceSettings::save($pdo,'default',hash('sha256',$changed['raw']),'quiet-elegant',[]);
verifyInstall(str_contains((new PublicSite($pdo))->render('/'),'data-dendrila-theme="quiet-elegant"'),'Appearance can revert without changing content');

$actor=new ActorContext('default','owner');$store=new PdoCmsStore($pdo);$authz=new AuthorizationService($store);$content=new ContentService($store,$authz);
$core=new CoreData($store,new NavigationService(new PdoNavigationRepository($pdo),$store,$authz));
$id=$core->createPage($actor,'presentation','Présentation','presentation');
$core->saveBlocks($actor,$id,1,[0=>['text'=>'Le corps de la page']],[]);
verifyInstall((new PublicSite($pdo))->render('/presentation/')===null,'Draft invisible');
$core->publishPage($actor,$id,2);
verifyInstall(str_contains((new PublicSite($pdo))->render('/presentation/'),'Le corps de la page'),'Published body visible');
$core->saveBlocks($actor,$id,3,[0=>['text'=>'Modifications non publiées']],[]);
verifyInstall(!str_contains((new PublicSite($pdo))->render('/presentation/'),'Modifications non publiées'),'Draft edit does not leak');
$core->addPageToMenu($actor,$id,'main',1);
verifyInstall(str_contains((new PublicSite($pdo))->render('/'),'href="/presentation/"'),'Page added to public menu');
try{$core->saveBlocks(new ActorContext('default','unknown'),$id,4,[0=>['text'=>'intrusion']],[]);throw new RuntimeException('Unauthorized mutation');}catch(AccessDenied){}
$identity=new NativeIdentity($pdo,'default',$settings['DENDRILA_AUTH_KEY']);
verifyInstall(!$identity->login('owner@example.test',$password,$setupCode,'127.0.0.1'),'Setup TOTP cannot replay');
$future=Totp::code($secret,intdiv(time(),30)+1);
verifyInstall(!$identity->login('owner@example.test','wrong',$future,'127.0.0.1'),'Wrong password rejected');
verifyInstall($identity->login('owner@example.test',$password,$future,'127.0.0.1'),'Two factors authenticate');
verifyInstall($identity->currentActor()?->actorId==='owner','Session actor resolved');

verifyInstall(($settings['DENDRILA_IDENTITY_PROVIDER']??'')==='native',
    'Fresh installs enable native Mon Manager Web association without a site adapter.');
$manifest=\Dendrila\Cms\Api\MmwDiscoveryManifest::fromEnvironment(
    static fn(string $key):?string=>isset($settings[$key])?(string)$settings[$key]:null
);
verifyInstall($manifest['api_base_url']==='https://example.test'
    && $manifest['auth_methods']===['pairing_code']
    && $manifest['site_id']==='default',
    'Fresh CMS publishes a site-specific MMW discovery manifest.');
$runtime=\Dendrila\Cms\Api\IdentityRuntimeFactory::fromEnvironment($pdo,
    static fn(string $key):?string=>isset($settings[$key])?(string)$settings[$key]:null
);
verifyInstall($runtime->authentication instanceof \Dendrila\Cms\Api\AuthenticationService
    && $runtime->authenticator instanceof \Dendrila\Cms\Api\NativeMmwDeviceAuthenticator,
    'Native MMW identity is wired automatically by the installer.');
$mmw=new \Dendrila\Cms\Api\NativeMmwPairing($pdo,'default',$settings['DENDRILA_AUTH_KEY']);
$code=$mmw->issueCode($actor);
verifyInstall(preg_match('/^[0-9]{8}$/D',$code)===1,'Pairing code is eight digits.');
$known=$pdo->prepare('SELECT code_hash FROM native_mmw_pair_codes WHERE site_id=? AND actor_id=?');
$known->execute(['default','owner']);
verifyInstall($known->fetchColumn()!==$code,'Plaintext pairing codes are never stored.');
$paired=$runtime->authentication->pair(['code'=>$code,'device_name'=>'Test Android']);
verifyInstall($paired['status']==='authenticated'
    && preg_match('/^[a-f0-9]{64}$/D',$paired['device_token'])===1,
    'Native MMW code issues a revocable device token.');
$request=new \Dendrila\Cms\Http\Request('GET','/v1/config',[
    'x-lepotager-device-token'=>$paired['device_token'],
    'x-lepotager-protocol'=>'1',
], '');
$mobileActor=$runtime->authenticator->authenticate($request);
verifyInstall($mobileActor->siteId==='default'&&$mobileActor->actorId==='owner',
    'Device token restores real CMS membership and permissions.');
$devices=$mmw->devices($actor);
verifyInstall(count($devices)===1,'Paired Android device is visible in the back-office.');
$mmw->revoke($actor,$devices[0]['device_id']);
try{$runtime->authenticator->authenticate($request);throw new RuntimeException('Revoked device was accepted.');}
catch(\Dendrila\Cms\Api\Unauthorized){}
try{$mmw->pair($code);throw new RuntimeException('Pairing code was reusable.');}
catch(\Dendrila\Cms\Api\AuthenticationFailure){}

verifyInstall(!$identity->login('owner@example.test',$password,$future,'127.0.0.1'),'Login code cannot replay');
$accounts=new Accounts($pdo,'default',$settings['DENDRILA_AUTH_KEY']);$token=$accounts->invite($actor,'member@example.test','member');$memberSecret=Totp::secret();
$accounts->redeem($token,$password,$memberSecret,Totp::code($memberSecret,intdiv(time(),30)));
try{$accounts->redeem($token,$password,$memberSecret,Totp::code($memberSecret,intdiv(time(),30)));throw new RuntimeException('Invitation replay');}catch(InvalidArgumentException){}
$member=$pdo->query("SELECT actor_id FROM native_accounts WHERE email='member@example.test'")->fetchColumn();
verifyInstall(!$store->actorHasPermission('default',$member,'content.update')&&$store->actorHasPermission('default',$member,'forum.post'),'Member role least privilege');
$accounts->disable($actor,$member);
verifyInstall(!$identity->login('member@example.test',$password,Totp::code($memberSecret,intdiv(time(),30)+1),'127.0.0.2'),'Disabled account cannot log in');
$media=MediaFactory::fromEnvironment($pdo);$path=$base.'/fixture.png';$image=imagecreatetruecolor(20,20);imagepng($image,$path);
$upload=$media->upload($actor,['module_id'=>'media_library','item_id'=>'media_library','client_request_id'=>'install-test-photo','metadata'=>'{"title":"Photo","alt":"Description"}'],new \Dendrila\Cms\Http\UploadedFile($path,'photo.png'));
$gallery=$core->createPage($actor,'gallery','Galerie','galerie');$mediaId=$upload['result']['id']??$upload['id']??null;
if(!$mediaId){$items=$media->items($actor,$media->module($actor,'media_library'));$mediaId=$items[0]['id'];}
$core->saveBlocks($actor,$gallery,1,[0=>['media_ids'=>[$mediaId]]],[$mediaId]);$core->publishPage($actor,$gallery,2);
$galleryHtml=(new PublicSite($pdo))->render('/galerie/');
verifyInstall(str_contains($galleryHtml,'media.php')&&str_contains($galleryHtml,'alt="Description"'),'Uploaded gallery preserves its media alternative');
// The SQL uniqueness constraint protects even slugs beyond the first UI batch.
for($i=0;$i<105;$i++)$core->createPage($actor,'free','Volume '.$i,'volume-'.$i);
verifyInstall(count($core->pages($actor))===108,'Editor includes pages beyond the first 100');
try{$core->createPage($actor,'free','Duplicate','presentation');throw new RuntimeException('Duplicate after 100 accepted');}catch(DomainException){}
// Server-side ownership, publication and account administration remain distinct.
foreach(['editor','contributor','viewer'] as $role){
    $q=$pdo->prepare('INSERT INTO memberships VALUES (?,?,?,UTC_TIMESTAMP(6))');$q->execute(['default','test-'.$role,$role]);
}
$contributor=new ActorContext('default','test-contributor');
$own=$core->createPage($contributor,'presentation','Contribution','contribution');
$core->saveBlocks($contributor,$own,1,[0=>['text'=>'Contribution privée']],[]);
foreach([new ActorContext('default','test-viewer'),$contributor,new ActorContext('default',$member)] as $limited){
    try{$core->saveBlocks($limited,$id,4,[0=>['text'=>'forbidden']],[]);throw new RuntimeException('Foreign page changed');}catch(AccessDenied|\Dendrila\Cms\Content\ContentNotFound){}
    try{$accounts->invite($limited,'forbidden@example.test','member');throw new RuntimeException('Unauthorized invitation');}catch(AccessDenied){}
}
try{$core->publishPage($contributor,$own,2);throw new RuntimeException('Contributor published');}catch(AccessDenied){}
$editor=new ActorContext('default','test-editor');$core->saveBlocks($editor,$own,2,[0=>['text'=>'Révision éditoriale']],[]);
verifyInstall((fileperms($base.'/config/local.php')&0777)===0600,'Configuration is private');
$lock=fopen($base.'/config/install.lock','c');flock($lock,LOCK_EX|LOCK_NB);
try{Installer::install(['theme'=>'quiet-elegant','addons'=>[],'secret'=>$secret,'db'=>$db],['name'=>'Test','email'=>'test@example.test','password'=>$password,'url'=>'https://example.test','code'=>Totp::code($secret,intdiv(time(),30))],$base.'/config');throw new LogicException('Concurrent installer passed lock');}catch(RuntimeException $e){verifyInstall(str_contains($e->getMessage(),'en cours'),'Concurrent installer rejected before DB changes');}finally{flock($lock,LOCK_UN);fclose($lock);}
$blockPage=$core->createPage($actor,'free','Tous les blocs','tous-les-blocs');$version=1;
foreach(['text','rich_text','gallery','cta','cards','faq','key_values','statistics'] as $type){$core->changeBlock($actor,$blockPage,$version++,'add',0,$type);}
$core->changeBlock($actor,$blockPage,$version++,'add',0,'embed_safe','',0,['provider'=>'youtube-nocookie','url'=>'https://www.youtube-nocookie.com/embed/example']);
$core->publishPage($actor,$blockPage,$version);
$html=(new PublicSite($pdo))->render('/tous-les-blocs/');
verifyInstall(str_contains($html,'dendrila-privacy-consent-config')&&str_contains($html,'data-dendrila-src='),'Every offered block persists and publishes with working consent markup');
if(getenv('INSTALL_BACKUP_MANIFEST')){
    $manifest=getenv('INSTALL_BACKUP_MANIFEST');if(!is_dir(dirname($manifest)))mkdir(dirname($manifest),0700,true);
    $old=umask(0077);file_put_contents($manifest,json_encode(['root'=>$base,'totp_hash'=>hash('sha256',$secret)],JSON_THROW_ON_ERROR));umask($old);
}
echo "Fresh installation, publication, blocks, navigation, 2FA/replay, invitations, roles, revocation and media PDO tests passed.\n";
