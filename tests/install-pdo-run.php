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
Installer::install(['theme'=>'quiet-elegant','addons'=>['media','forum'],'secret'=>$secret,'db'=>$db],['name'=>'Installation Test','email'=>'owner@example.test','password'=>$password,'url'=>'https://example.test','code'=>Totp::code($secret,intdiv(time(),30))],$base.'/config');
$settings=require $base.'/config/local.php';foreach($settings as $k=>$v)putenv($k.'='.$v);
$pdo=new PDO($settings['DB_DSN'],$settings['DB_USERNAME'],$settings['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
verifyInstall((new Migrator($pdo))->run()===[],'Migrations repeat safely');
try{Installer::connect($db);throw new RuntimeException('Nonempty DB accepted');}catch(DomainException){}
verifyInstall(str_contains((new PublicSite($pdo))->render('/'),'Bienvenue sur notre site.'),'Fresh site serves a published homepage');
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
verifyInstall(!$identity->login('owner@example.test',$password,Totp::code($secret,intdiv(time(),30)),'127.0.0.1'),'Setup TOTP cannot replay');
$future=Totp::code($secret,intdiv(time(),30)+1);
verifyInstall(!$identity->login('owner@example.test','wrong',$future,'127.0.0.1'),'Wrong password rejected');
verifyInstall($identity->login('owner@example.test',$password,$future,'127.0.0.1'),'Two factors authenticate');
verifyInstall($identity->currentActor()?->actorId==='owner','Session actor resolved');
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
verifyInstall(str_contains((new PublicSite($pdo))->render('/galerie/'),'media.php'),'Uploaded gallery renders');
echo "Fresh installation, publication, blocks, navigation, 2FA/replay, invitations, roles, revocation and media PDO tests passed.\n";
