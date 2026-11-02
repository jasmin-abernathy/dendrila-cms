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
        ['theme'=>'ultra-minimal','addons'=>['media','forum','booking'],'booking_mode'=>'both','db'=>['driver'=>'sqlite'],'secret'=>$secret],
        ['name'=>'Example site','email'=>'owner@example.test',
         'password'=>'example-long-password-sqlite','url'=>'https://example.test',
         'code'=>Totp::code($secret,intdiv(time(),30))],
        $base.'/config'
    );
    $settings=require $base.'/config/local.php';
    if(($settings['DENDRILA_BOOKING_SETUP_REQUESTED']??null)!=='1')
        throw new RuntimeException('Booking request not retained.');
    if(($settings['DENDRILA_BOOKING_MODE']??null)!=='both'||($settings['DENDRILA_BOOKING_STORAGE_DRIVER']??null)!=='sqlite')
        throw new RuntimeException('Booking mode or storage not persisted.');
    if(in_array('booking',explode(',',$settings['DENDRILA_ADDONS']),true))
        throw new RuntimeException('Unconfigured Booking must never be marked active.');
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
    // Site-level terms, scoped page terms and metadata work after an installed
    // SQLite schema with no extra migration or external SEO provider.
    $topics=\Dendrila\Cms\Seo\SeoKeywordSettings::read($pdo,'default');
    \Dendrila\Cms\Seo\SeoKeywordSettings::save($pdo,'default',
        hash('sha256',$topics['raw']),['services administratifs','documents'],
        [$id['content']['id']=>['services administratifs']]);
    $topics=\Dendrila\Cms\Seo\SeoKeywordSettings::read($pdo,'default');
    if($topics['keywords']!==['services administratifs','documents'])
        throw new RuntimeException('SEO site topic bank did not persist in SQLite.');
    $seoSvc=new \Dendrila\Cms\Seo\SeoService(
        new \Dendrila\Cms\Seo\PdoSeoRepository($pdo),$store,$auth);
    $seoSvc->save($actor,$id['content']['id'],[
        'title'=>'Services administratifs — exemple',
        'description'=>'Détails pratiques sur les documents et les démarches administratives.',
        'canonical_url'=>'/sqlite-test/',
        'social_title'=>null,'social_description'=>null,'social_image_url'=>null,
        'indexable'=>true,
    ],0);
    $result=(new PublicSite($pdo))->render('/sqlite-test/');
    if(!str_contains($result,'<title>Services administratifs — exemple</title>')
       || !str_contains($result,'name="description"')
       || !str_contains($result,'name="keywords" content="services administratifs"')
       || !str_contains($result,'rel="canonical" href="https://example.test/sqlite-test/"'))
        throw new RuntimeException('Live SQLite page does not emit configured SEO tags.');
    $advisor=\Dendrila\Cms\Seo\SeoWritingAdvisor::review(
        ['title'=>'Documents','blocks'=>[['type'=>'text','text'=>'Détails sur les documents administratifs.']]],
        ['description'=>''],['services administratifs']);
    if(!$advisor['checks'] || !$advisor['total'])
        throw new RuntimeException('Local writing recommendations not computed.');
    try{
        \Dendrila\Cms\Seo\SeoKeywordSettings::save($pdo,'default',
            hash('sha256','stale'),[],[]);
        throw new RuntimeException('Stale site SEO settings accepted.');
    }catch(\DomainException $expected){}
    // A rejected form must release its manually opened SQLite write lock.
    $peer=new SqliteConnection($settings['DB_DSN']);
    $peer->exec('BEGIN IMMEDIATE');
    $peer->exec('ROLLBACK');
    // A caller-owned transaction remains open and can undo SEO changes.
    $before=\Dendrila\Cms\Seo\SeoKeywordSettings::read($pdo,'default');
    $pdo->beginTransaction();
    \Dendrila\Cms\Seo\SeoKeywordSettings::save($pdo,'default',
        hash('sha256',$before['raw']),['temporary'],[]);
    if(!$pdo->inTransaction())throw new RuntimeException('SEO committed the caller transaction.');
    $pdo->rollBack();
    if(\Dendrila\Cms\Seo\SeoKeywordSettings::read($pdo,'default')['raw']!==$before['raw'])
        throw new RuntimeException('SEO changes escaped the caller rollback.');
    // Appearance editor must work on the default SQLite installation too.
    $startAppearance=\Dendrila\Cms\Runtime\AppearanceSettings::read($pdo,'default');
    $brand=['primary'=>'#9d2941','text'=>'#161616','background'=>'#ffffff'];
    \Dendrila\Cms\Runtime\AppearanceSettings::save($pdo,'default',
        hash('sha256',$startAppearance['raw']),'ultra-minimal',$brand);
    $savedAppearance=\Dendrila\Cms\Runtime\AppearanceSettings::read($pdo,'default');
    if($savedAppearance['brand']!==$brand)
        throw new RuntimeException('SQLite appearance choices did not persist.');
    if(!str_contains((string)(new PublicSite($pdo))->render('/'),'#9d2941'))
        throw new RuntimeException('Saved SQLite palette does not reach the public site.');
    try {
        \Dendrila\Cms\Runtime\AppearanceSettings::save($pdo,'default',
            hash('sha256',$startAppearance['raw']),'editorial',[]);
        throw new RuntimeException('Stale SQLite appearance form accepted.');
    }catch(\DomainException $expected){}
    $peer->exec('BEGIN IMMEDIATE');
    $peer->exec('ROLLBACK');
    \Dendrila\Cms\Runtime\AppearanceSettings::save($pdo,'default',
        hash('sha256',$savedAppearance['raw']),'ultra-minimal',[]);
    if(\Dendrila\Cms\Runtime\AppearanceSettings::read($pdo,'default')['brand']!==[])
        throw new RuntimeException('Reset to native theme colors failed on SQLite.');
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
