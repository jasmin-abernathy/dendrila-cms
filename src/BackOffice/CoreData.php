<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Navigation\NavigationService;
use Dendrila\Cms\Storage\CmsStore;

/** Raccordement aux primitives CMS existantes, sans réécrire les règles métier. */
final readonly class CoreData
{
    private AuthorizationService $authorization;
    private ContentService $content;

    public function __construct(private CmsStore $store, private NavigationService $navigation)
    {
        $this->authorization=new AuthorizationService($store);
        $this->content=new ContentService($store,$this->authorization);
    }

    public function can(ActorContext $actor,string $permission): bool
    {
        return $this->store->actorHasPermission($actor->siteId,$actor->actorId,$permission);
    }

    /** @return list<array<string,mixed>> */
    public function pages(ActorContext $actor): array
    {
        if (!$this->can($actor,'content.read')) return [];
        $pages=$this->content->list($actor,['type'=>'page','limit'=>100]);
        $order=[];
        if ($this->can($actor,'navigation.read')) {
            foreach ($this->navigation->list($actor) as $menu) {
                if ($menu['location']!=='primary') continue;
                foreach ($menu['items'] as $index=>$item) {
                    $order[rtrim((string)$item['url'],'/')?:'/']=$index;
                }
                break;
            }
        }
        usort($pages,static function(array $a,array $b) use($order): int {
            $route=static fn(array $p): string => ($p['slug']??'')==='home'?'/':'/'.trim((string)($p['slug']??''),'/');
            $key=static fn(array $p): array => [
                $route($p)==='/'?0:1,
                $order[$route($p)]??PHP_INT_MAX,
                (string)($p['values']['title']??$p['slug']??''),
            ];
            return $key($a)<=>$key($b);
        });
        return $pages;
    }

    /** @return array<string,mixed> */
    public function page(ActorContext $actor,string $id): array
    {
        $page=$this->content->get($actor,$id);
        if (($page['type_id']??'')!=='page') {
            throw new \DomainException('Cette entrée ne peut pas être modifiée ici.');
        }
        return $page;
    }

    /** @return list<array{id:string,label:string,type:string,required:bool}> */
    public function textFields(ActorContext $actor): array
    {
        $def=$this->store->findContentType($actor->siteId,'page');
        if(!is_array($def)||!is_array($def['fields']??null))return [];
        $fields=[];
        foreach($def['fields'] as $field){
            if(!in_array($field['type']??'',['text','multiline','rich_text'],true))continue;
            // Pas de saisie de code ou de champs système depuis cette interface.
            if (!preg_match('/^[a-z][a-z0-9_]*$/D',(string)($field['id']??'')))continue;
            if (in_array($field['id'], ['slug','url','path'], true)) continue;
            $fields[]=['id'=>$field['id'],'label'=>(string)($field['label']??$field['id']),
                'type'=>$field['type'],'required'=>(bool)($field['required']??false)];
        }
        return $fields;
    }

    /** @param array<string,mixed> $fields */
    public function savePage(ActorContext $actor,string $id,int $version,array $fields): void
    {
        $page=$this->page($actor,$id);
        $allowed=array_column($this->textFields($actor),'id');
        if(array_diff(array_keys($fields),$allowed)!==[])throw new \InvalidArgumentException('Champ inconnu.');
        $values=array_replace($page['values'],$fields);
        $this->content->updateDraft($actor,$id,$version,$values,bin2hex(random_bytes(16)));
    }

    public function publishPage(ActorContext $actor,string $id,int $version): void
    {
        $this->page($actor,$id);
        $this->content->transition($actor,$id,'published',$version,bin2hex(random_bytes(16)));
    }

    /**
     * Crée une page éditoriale, avec un modèle de départ adapté.
     * Les métadonnées sont validées par ContentService et son schéma de site.
     * @return string Identifiant interne du brouillon.
     */
    public function createPage(ActorContext $actor,string $template,string $title,string $slug): string
    {
        $this->authorization->require($actor,'content.create');
        if(!in_array($template,['presentation','gallery','free'],true))
            throw new \InvalidArgumentException('Type de page non reconnu.');
        $title=trim($title);
        $slug=trim($slug);
        if($title==='' || mb_strlen($title)>240 ||
           !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/D',$slug) ||
           in_array($slug,['admin','api','assets','forum','galeries','pages','actualites'],true))
            throw new \InvalidArgumentException('Titre ou adresse invalide.');
        if($this->store->findContentType($actor->siteId,'page')===null)
            throw new \DomainException('Ce site ne propose pas de pages.');
        // Ne pas écraser une page existante, publiée ou en brouillon.
        foreach($this->content->list($actor,['type'=>'page','limit'=>100]) as $existing) {
            if(($existing['slug']??null)===$slug)
                throw new \DomainException('Cette adresse est déjà utilisée.');
        }
        $blocks=match($template) {
            'presentation'=>[['type'=>'text','text'=>'']],
            'gallery'=>[['type'=>'gallery','media_ids'=>[]]],
            'free'=>[],
        };
        $created=$this->content->createDraft($actor,'page',[
            'title'=>$title,'slug'=>$slug,'blocks'=>$blocks,
        ],bin2hex(random_bytes(16)));
        return (string)$created['content']['id'];
    }

    /** @return list<array<string,mixed>> */
    public function menus(ActorContext $actor): array
    {
        if (!$this->can($actor,'navigation.read'))return [];
        return $this->navigation->list($actor);
    }

    /** @param list<string> $order @param array<string,string> $labels @param array<string,mixed> $shown */
    public function reorderMenu(ActorContext $actor,string $menuId,int $version,array $order,array $labels,array $shown): void
    {
        $menu=$this->navigation->get($actor,$menuId);
        $items=$menu['items'];
        $byId=array_column($items,null,'id');
        $ids=array_keys($byId);
        if(count($ids)!==count($order)||count($ids)!==count($labels)
            || array_diff($ids,$order)!==[]||array_diff($order,$ids)!==[]
            || count(array_unique($order))!==count($order)
            || array_diff(array_keys($labels),$ids)!==[]
            || array_diff($ids,array_keys($labels))!==[]
            || array_diff(array_keys($shown),$ids)!==[]) {
            throw new \InvalidArgumentException('Le menu est incomplet ou contient des doublons.');
        }
        $reordered=[];
        foreach($order as $id) {
            $label=trim($labels[$id]);
            if($label===''||mb_strlen($label)>190)throw new \InvalidArgumentException('Libellé de menu invalide.');
            $existing=$byId[$id];
            $reordered[]=['id'=>$id,'label'=>$label,'url'=>$existing['url'],
                'visible'=>$existing['url']==='/' || array_key_exists($id,$shown)];
        }
        $this->navigation->save($actor,$menuId,$menu['label'],$menu['location'],$reordered,$version);
    }
}
