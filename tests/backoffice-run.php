<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use Dendrila\Cms\BackOffice\Config;
use Dendrila\Cms\BackOffice\CoreData;
use Dendrila\Cms\BackOffice\IdentityProvider;
use Dendrila\Cms\BackOffice\Kernel;
use Dendrila\Cms\BackOffice\SessionCsrf;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Navigation\NavigationRepository;
use Dendrila\Cms\Navigation\NavigationService;
use Dendrila\Cms\Tests\Support\InMemoryCmsStore;

function backofficeCheck(bool $condition,string $message): void
{
    if(!$condition) throw new RuntimeException($message);
}
$store=new InMemoryCmsStore();
$site='demo';
$owner=new ActorContext($site,'owner');
$store->permissions[$site]['owner']=[
    'content.create','content.read','content.read_draft','content.update','content.publish',
    'navigation.read','navigation.manage','users.read','users.manage','media.read','media.upload',
    'content.publish_without_review'
];
$store->permissions[$site]['visitor']=['content.read'];
$store->types[$site]['page']=json_decode((string)file_get_contents(dirname(__DIR__).'/examples/content-types/page.json'),true,512,JSON_THROW_ON_ERROR);
$repo=new class implements NavigationRepository {
    public array $menus=[];
    public function find(string $s,string $id):?array{return $this->menus[$s][$id]??null;}
    public function findForUpdate(string $s,string $id):?array{return $this->find($s,$id);}
    public function findByLocation(string $s,string $loc):?array{
        foreach($this->menus[$s]??[] as $row)if($row['location']===$loc)return $row;
        return null;
    }
    public function list(string $s):array{return array_values($this->menus[$s]??[]);}
    public function insert(array $menu):void{$this->menus[$menu['site_id']][$menu['menu_id']]=$menu;}
    public function save(array $menu,int $version):void{
        if(($this->menus[$menu['site_id']][$menu['menu_id']]['version']??0)!==$version)
          throw new RuntimeException('Stale test menu');
        $this->menus[$menu['site_id']][$menu['menu_id']]=$menu;
    }
    public function delete(string $s,string $id,int $version):void{unset($this->menus[$s][$id]);}
};
$authz=new AuthorizationService($store);
$contents=new ContentService($store,$authz);
$navigation=new NavigationService($repo,$store,$authz);
$created=$contents->createDraft($owner,'page',['title'=>'Page d’accueil','slug'=>'home','blocks'=>[]],'setup-home');
$id=$created['content']['id'];
$second=$contents->createDraft($owner,'page',['title'=>'Contact','slug'=>'contact','blocks'=>[]],'setup-contact');
$navigation->save($owner,'main','Menu principal','primary',[
    ['id'=>'home','label'=>'Accueil','url'=>'/'],
    ['id'=>'contact','label'=>'Contact','url'=>'/contact'],
],0);
$identity=new class implements IdentityProvider {
    public ?ActorContext $actor=null;
    public function currentActor():?ActorContext{return $this->actor;}
};
$config=new Config(siteId:$site,siteName:'Démonstration & CMS',basePath:'/administration/',
    assetsPath:'/backoffice-assets',primaryColor:'#336d4e',iconUrl:'/favicon.ico');
$csrf=new SessionCsrf();
session_start();
$controller=new Kernel($config,$identity,$csrf,new CoreData($store,$navigation));
$guest=$controller->handle('GET');
backofficeCheck($guest->status===401 && str_contains($guest->body,'Connectez-vous'),
    'Anonymous users cannot access the editor.');
$identity->actor=new ActorContext('other-site','owner');
backofficeCheck($controller->handle('GET')->status===401,'Foreign site identities must not open this admin.');
$identity->actor=$owner;
$manifest=$controller->handle('GET',['manifest'=>'1']);
$manifestData=json_decode($manifest->body,true,512,JSON_THROW_ON_ERROR);
backofficeCheck($manifest->status===200 && $manifestData['scope']==='/administration/' &&
    $manifestData['display']==='standalone','The optional PWA is restricted to the admin scope.');
$theme=$controller->handle('GET',['theme'=>'1']);
backofficeCheck($theme->status===200 && str_contains($theme->body,'#336d4e'),'Brand theming must be server controlled.');
$page=$controller->handle('GET',['section'=>'textes','page'=>$id]);
backofficeCheck($page->status===200 && str_contains($page->body,'Page d’accueil'),'Page selector must display page name.');
backofficeCheck(!str_contains($page->body,'published · version'),'Technical badges must be hidden.');
backofficeCheck(str_contains($page->body,'fields[title]') && str_contains($page->body,'<noscript>'),
    'The editor needs declared fields and no-JS fallback.');
backofficeCheck(str_contains($page->body,'Démonstration &amp; CMS'),'Site names must be HTML escaped.');
$badCsrf=$controller->handle('POST',[],['action'=>'save_page','csrf'=>'invalid']);
backofficeCheck($badCsrf->status===403,'Forged POST requests must fail.');
$csrfToken=$csrf->token();
$save=$controller->handle('POST',[],[
    'action'=>'save_page','csrf'=>$csrfToken,'page_id'=>$id,'version'=>'1',
    'fields'=>['title'=>"Une première ligne\nUne seconde ligne"],
]);
backofficeCheck($save->status===303 && str_contains($save->headers['Location']??'','page='),
    'Page editing must redirect after a successful save.');
$updated=$contents->get($owner,$id);
backofficeCheck($updated['values']['title']==="Une première ligne\nUne seconde ligne" && $updated['version']===2,
    'The common editor must use the genuine versioned CMS ContentService.');
$stale=$controller->handle('POST',[],[
    'action'=>'save_page','csrf'=>$csrfToken,'page_id'=>$id,'version'=>'1','fields'=>['title'=>'Stale'],
]);
backofficeCheck($stale->status===409,'Concurrent content edits must not be overwritten.');
$unknown=$controller->handle('POST',[],[
    'action'=>'save_page','csrf'=>$csrfToken,'page_id'=>$id,'version'=>'2','fields'=>['unknown'=>'x'],
]);
backofficeCheck($unknown->status===422,'Undeclared CMS fields must be rejected.');
$createScreen=$controller->handle('GET',['section'=>'textes']);
backofficeCheck(str_contains($createScreen->body,'Nouvelle page') &&
    str_contains($createScreen->body,'value="gallery"') &&
    str_contains($createScreen->body,'value="free"'),
    'Page creation offers classic, gallery, and free templates.');
$createGallery=$controller->handle('POST',[],[
    'action'=>'create_page','csrf'=>$csrfToken,'template'=>'gallery',
    'title'=>'Nos nouvelles photographies','slug'=>'nouvelles-photographies',
]);
backofficeCheck($createGallery->status===303 &&
    str_contains($createGallery->headers['Location']??'','created=1'),
    'Creating a gallery draft redirects to the editor.');
$createdGalleryId=(string)($store->contents[$site] ? array_key_last($store->contents[$site]) : '');
$galleryItems=$contents->list($owner,['type'=>'page']);
$gallery=array_values(array_filter($galleryItems,static fn(array $p):bool=>
    ($p['slug']??null)==='nouvelles-photographies'));
backofficeCheck(count($gallery)===1 && $gallery[0]['status']==='draft' &&
    ($gallery[0]['values']['blocks'][0]['type']??'')==='gallery',
    'Gallery template must create a private page with a native gallery block.');
$dupGallery=$controller->handle('POST',[],[
    'action'=>'create_page','csrf'=>$csrfToken,'template'=>'gallery',
    'title'=>'Doublon','slug'=>'nouvelles-photographies',
]);
backofficeCheck($dupGallery->status===422,'Duplicate slugs must be rejected.');
$unknownType=$controller->handle('POST',[],[
    'action'=>'create_page','csrf'=>$csrfToken,'template'=>'external',
    'title'=>'Invalide','slug'=>'invalide',
]);
backofficeCheck($unknownType->status===422,'Unknown types must be rejected.');
$menu=$controller->handle('GET',['section'=>'menu']);
backofficeCheck($menu->status===200 && str_contains($menu->body,'data-sortable')
    && !str_contains($menu->body,'Position 10'),'Menu must be a draggable list without numeric positions.');
$reorder=$controller->handle('POST',[],[
    'action'=>'save_menu','csrf'=>$csrfToken,'menu_id'=>'main','version'=>'1',
    'order'=>['contact','home'],'labels'=>['contact'=>'Échanger','home'=>'Accueil'],'shown'=>['contact'=>'1'],
]);
backofficeCheck($reorder->status===303,'Menu reorder should succeed.');
$menuData=$navigation->get($owner,'main');
backofficeCheck(array_column($menuData['items'],'id')===['contact','home'] &&
    $menuData['items'][1]['visible']===true,
    'Menu order is saved, and the homepage remains visible.');
$duplicate=$controller->handle('POST',[],[
    'action'=>'save_menu','csrf'=>$csrfToken,'menu_id'=>'main','version'=>'2',
    'order'=>['contact','contact'],'labels'=>['contact'=>'Échanger','home'=>'Accueil'],
]);
backofficeCheck($duplicate->status===422,'Duplicate menu entries must be rejected.');
backofficeCheck(!str_contains($controller->handle('GET',['section'=>'tableau'])->body,'Utilisateurs</a>')
    && !str_contains($controller->handle('GET',['section'=>'tableau'])->body,'Photos</a>'),
    'Absent media and user adapters must not appear as working management tabs.');
$identity->actor=new ActorContext($site,'visitor');
$readOnly=$controller->handle('GET',['section'=>'menu']);
backofficeCheck($readOnly->status===200 && !str_contains($readOnly->body,'data-sort-handle'),
    'Read-only users must not be offered menu mutation controls.');
$forbidden=$controller->handle('POST',[],[
    'action'=>'save_menu','csrf'=>$csrfToken,'menu_id'=>'main','version'=>'2',
    'order'=>['home','contact'],'labels'=>['contact'=>'Contact','home'=>'Accueil'],
]);
backofficeCheck($forbidden->status===403,'Permissions are enforced on POST server side.');
$media=new class implements \Dendrila\Cms\BackOffice\MediaPort {
    public function list(ActorContext $actor):array{
        return [['id'=>'photo-1','title'=>'Photo & rénovation','alt'=>'Escalier rénové',
            'preview_url'=>'/uploads/galerie/escaliers.webp']];
    }
    public function upload(ActorContext $actor,array $file,string $title,string $alt):void {
        throw new LogicException('Upload end-to-end requires a legitimate HTTP upload and site media adapter.');
    }
};
$users=new class implements \Dendrila\Cms\BackOffice\UsersPort {
    public array $invites=[];
    public function list(ActorContext $actor):array {
        return [['label'=>'Responsable BMH','email'=>'admin@example.test','role'=>'owner']];
    }
    public function invite(ActorContext $actor,string $email,string $role):void {
        $this->invites[]=[$email,$role];
    }
};
$identity->actor=$owner;
$extended=new Kernel($config,$identity,$csrf,new CoreData($store,$navigation),$media,$users);
$mediaPage=$extended->handle('GET',['section'=>'medias']);
backofficeCheck($mediaPage->status===200
    && str_contains($mediaPage->body,'Photo &amp; rénovation')
    && !str_contains($mediaPage->body,'Adresse de l’image'),
    'Optional photo port displays a usable library without technical URLs.');
$galleryId=$gallery[0]['content_id'];
$galleryView=$extended->handle('GET',['section'=>'textes','page'=>$galleryId]);
backofficeCheck(str_contains($galleryView->body,'blocks[0][media_ids][]') &&
    str_contains($galleryView->body,'photo-1'),
    'A gallery can select photos from a connected native media port.');
$gallerySave=$extended->handle('POST',[],[
    'action'=>'save_gallery_photos','csrf'=>$csrfToken,'page_id'=>$galleryId,
    'version'=>'1','media_ids'=>['photo-1'],
]);
backofficeCheck($gallerySave->status===303 &&
    $contents->get($owner,$galleryId)['values']['blocks'][0]['media_ids']===['photo-1'],
    'Selected photos are persisted in the native gallery block.');
$badPhoto=$extended->handle('POST',[],[
    'action'=>'save_gallery_photos','csrf'=>$csrfToken,'page_id'=>$galleryId,
    'version'=>'2','media_ids'=>['foreign-photo'],
]);
backofficeCheck($badPhoto->status===422 &&
    $contents->get($owner,$galleryId)['version']===2,
    'Unknown photos cannot be linked to a gallery.');
$usersPage=$extended->handle('GET',['section'=>'utilisateurs']);
backofficeCheck($usersPage->status===200 && str_contains($usersPage->body,'Envoyer l’invitation'),
    'Optional user port can display invitation flow.');
$invalidInvite=$extended->handle('POST',[],[
    'action'=>'invite_user','csrf'=>$csrfToken,'email'=>'not-an-email','role'=>'editor',
]);
backofficeCheck($invalidInvite->status===422 && $users->invites===[],
    'Invalid invitation data never reaches the identity provider.');
$validInvite=$extended->handle('POST',[],[
    'action'=>'invite_user','csrf'=>$csrfToken,'email'=>'editor@example.test','role'=>'editor',
]);
backofficeCheck($validInvite->status===303
    && $users->invites===[['editor@example.test','editor']],
    'User invitations are delegated only to the trusted identity port.');
$identity->actor=new ActorContext($site,'visitor');
backofficeCheck($extended->handle('GET',['section'=>'utilisateurs'])->status===200
    && !str_contains($extended->handle('GET',['section'=>'utilisateurs'])->body,'Envoyer l’invitation'),
    'Read-only actors cannot create users.');
$identity->actor=$owner;
$textFields=(new CoreData($store,$navigation))->textFields($owner);
backofficeCheck(!in_array('slug',array_column($textFields,'id'),true),
    'Technical URL and slug fields must stay outside the ordinary text editor.');
$unsafePathEdit=$controller->handle('POST',[],[
    'action'=>'save_page','csrf'=>$csrfToken,'page_id'=>$id,'version'=>'2',
    'fields'=>['slug'=>'broken-link'],
]);
backofficeCheck($unsafePathEdit->status===422,
    'A forged form cannot mutate technical page URLs.');
$otherConfig=new Config(siteId:$site,siteName:'BMH Rénovation',
    basePath:'/espace-client/',assetsPath:'/espace-client/assets',
    primaryColor:'#e2741f',iconUrl:'/assets/icons/apple-touch-icon.png',
    loginUrl:'/espace-client/login.php');
$otherKernel=new Kernel($otherConfig,$identity,$csrf,new CoreData($store,$navigation));
$otherManifest=json_decode($otherKernel->handle('GET',['manifest'=>'1'])->body,true);
backofficeCheck($otherManifest['scope']==='/espace-client/'
    && str_contains($otherKernel->handle('GET',['section'=>'appli'])->body,'BMH Rénovation'),
    'The same codebase supports a completely different site identity and admin base path.');
try { new Config(siteId:$site,siteName:'Unsafe',iconUrl:'javascript:alert(1)'); }
catch (InvalidArgumentException $expected) { $rejected=true; }
backofficeCheck($rejected??false,'Unsafe icon URLs are rejected by configuration.');
echo "Common BackOffice tests passed: authentication, CSRF, types, revisions, menu, optional ports and PWA.\n";
