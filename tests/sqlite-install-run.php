<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Installation\Installer;
use Dendrila\Cms\Auth\Totp;
use Dendrila\Cms\Runtime\PublicSite;
use Dendrila\Cms\Storage\SqliteConnection;

$base=sys_get_temp_dir().'/dendrila-sqlite-test-'.bin2hex(random_bytes(8));
mkdir($base.'/config',0700,true);
$secret=Totp::secret();
try {
    Installer::install(
        ['theme'=>'ultra-minimal','addons'=>['media','forum'],'db'=>['driver'=>'sqlite'],'secret'=>$secret],
        ['name'=>'Example site','email'=>'owner@example.test',
         'password'=>'example-long-password-sqlite','url'=>'https://example.test',
         'code'=>Totp::code($secret,intdiv(time(),30))],
        $base.'/config'
    );
    $settings=require $base.'/config/local.php';
    foreach($settings as $key=>$value) putenv($key.'='.$value);
    $pdo=new SqliteConnection($settings['DB_DSN']);
    $dbFile=substr($settings['DB_DSN'],7);
    if(basename($dbFile)!=='site.sqlite'||!is_file($dbFile)
        ||(fileperms($dbFile)&0777)!==0600)
        throw new RuntimeException('SQLite file must be a private 0600 file.');
    if((new \Dendrila\Cms\Installation\SqliteMigrator($pdo))->run()!==[])
        throw new RuntimeException('SQLite migrations must be idempotent.');
    if((int)$pdo->query('SELECT COUNT(*) FROM contents')->fetchColumn()<4)
        throw new RuntimeException('Starter pages not installed.');
    if((int)$pdo->query('SELECT COUNT(*) FROM native_accounts')->fetchColumn()!==1)
        throw new RuntimeException('Owner account not installed.');
    $html=(new PublicSite($pdo))->render('/');
    if(!is_string($html) || !str_contains($html,'data-dendrila-theme'))
        throw new RuntimeException('Published homepage unavailable.');
    $actor=new \Dendrila\Cms\Authorization\ActorContext('default','owner');
    $store=new \Dendrila\Cms\Storage\PdoCmsStore($pdo);
    $auth=new \Dendrila\Cms\Authorization\AuthorizationService($store);
    $content=new \Dendrila\Cms\Content\ContentService($store,$auth);
    $id=$content->createDraft($actor,'page',[
        'title'=>'Test SQLite','slug'=>'sqlite-test',
        'blocks'=>[['type'=>'text','text'=>'Vérification réelle SQLite']]
    ],'sqlite-test-draft');
    $content->transition($actor,$id['content']['id'],'published',1,'sqlite-test-publish');
    if(!str_contains((string)(new PublicSite($pdo))->render('/sqlite-test/'),'Vérification réelle SQLite'))
        throw new RuntimeException('SQLite published page not visible.');
    session_start();
    $identity=new \Dendrila\Cms\Auth\NativeIdentity($pdo,'default',$settings['DENDRILA_AUTH_KEY']);
    if($identity->login('owner@example.test','invalid-password',
        Totp::code($secret,intdiv(time(),30)+1),'127.0.0.1'))
        throw new RuntimeException('Incorrect password accepted.');
    if(!$identity->login('owner@example.test','example-long-password-sqlite',
        Totp::code($secret,intdiv(time(),30)+1),'127.0.0.1'))
        throw new RuntimeException('Correct credentials rejected.');
    echo "SQLite installed: owner login, editable and published pages, media/forum addon schema OK\n";
} finally {
    foreach(glob($base.'/config/*')?:[] as $path) @unlink($path);
    foreach(glob($base.'/storage/*')?:[] as $path) @unlink($path);
    @rmdir($base.'/config');
    @rmdir($base.'/storage');
    @rmdir($base);
}
