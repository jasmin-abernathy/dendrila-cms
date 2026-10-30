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
        // Fetch every permitted page rather than silently hiding those after the first batch.
        $pages=[];
        for($offset=0;$offset<=100000;$offset+=100){
            $batch=$this->content->list($actor,['type'=>'page','limit'=>100,'offset'=>$offset]);
            array_push($pages,...$batch);
            if(count($batch)<100)break;
        }
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
        $page=$this->page($actor,$id);
        $references=\Dendrila\Cms\Media\MediaReferences::ids($this->store->findContentType($actor->siteId,'page')??[], $page['values']);
        $map=array_fill_keys($references,'/assets/preview.svg');
        (new \Dendrila\Cms\Front\PublicPageRenderer())->render(['name'=>'Dendrila'],$page['values'],['design_kit'=>['id'=>'core']],'/assets/site.css',$map);
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
           in_array($slug,['admin','api','assets','forum','galeries','pages','actualites','backoffice-assets','v1','install','media','theme-preview','router'],true))
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
        try{$created=$this->content->createDraft($actor,'page',[
            'title'=>$title,'slug'=>$slug,'blocks'=>$blocks,
        ],bin2hex(random_bytes(16)));}catch(\PDOException $e){
            if(($e->errorInfo[1]??null)===1062)throw new \DomainException('Cette adresse est déjà utilisée.');
            throw $e;
        }
        return (string)$created['content']['id'];
    }

    /** @param list<string> $ids */
    public function saveGalleryPhotos(ActorContext $actor,string $id,int $version,array $ids): void
    {
        $page=$this->page($actor,$id);
        $blocks=$page['values']['blocks']??null;
        if(!is_array($blocks) || ($blocks[0]['type']??null)!=='gallery')
            throw new \DomainException('Cette page n’est pas une galerie.');
        if(count($ids)>60 || count(array_unique($ids))!==count($ids) ||
            count(array_filter($ids,static fn(mixed $v):bool=>
                is_string($v)&&preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$v)===1))!==count($ids))
            throw new \InvalidArgumentException('Liste de photographies invalide.');
        $values=$page['values'];
        $values['blocks'][0]['media_ids']=$ids;
        $this->content->updateDraft($actor,$id,$version,$values,bin2hex(random_bytes(16)));
    }

    public function saveBlocks(ActorContext $actor,string $id,int $version,array $edits,array $mediaIds): void
    {
        $page=$this->page($actor,$id);$values=$page['values'];
        $values['blocks']=BlockEditor::update($values['blocks']??[],$edits,$mediaIds);
        $this->content->updateDraft($actor,$id,$version,$values,bin2hex(random_bytes(16)));
    }
    public function changeBlock(ActorContext $actor,string $id,int $version,string $operation,int $index,string $type='text',string $mediaId='',int $itemIndex=0,array $initial=[]):void
    {
        $page=$this->page($actor,$id);$values=$page['values'];$blocks=$values['blocks']??[];
        if($operation==='add') {
            if($type==='embed_safe'){
                if(!is_string($initial['url']??null)||$initial['url']==='')throw new \InvalidArgumentException('Indiquez l’adresse d’intégration dans les options du contenu externe.');
                $blocks[]=BlockEditor::update([BlockEditor::blank($type)],[0=>$initial],[])[0];
            }else $blocks[]=$type==='image'?['type'=>'image','media_id'=>$mediaId,'alt'=>'']:BlockEditor::blank($type);
        } else {
            if(!isset($blocks[$index]))throw new \InvalidArgumentException('Bloc introuvable.');
            if($operation==='remove')array_splice($blocks,$index,1);
            elseif($operation==='up'&&$index>0)[$blocks[$index-1],$blocks[$index]]=[$blocks[$index],$blocks[$index-1]];
            elseif($operation==='down'&&$index<count($blocks)-1)[$blocks[$index+1],$blocks[$index]]=[$blocks[$index],$blocks[$index+1]];
            elseif(in_array($operation,['add_item','remove_item','item_up','item_down'],true)) {
                $blocks[$index]=BlockEditor::collection($blocks[$index],$operation,$itemIndex);
            } else throw new \InvalidArgumentException('Opération invalide.');
        }
        $values['blocks']=$blocks;
        $this->content->updateDraft($actor,$id,$version,$values,bin2hex(random_bytes(16)));
    }
    public function addPageToMenu(ActorContext $actor,string $pageId,string $menuId,int $version):void
    {
        $page=$this->page($actor,$pageId);$menu=$this->navigation->get($actor,$menuId);
        $url=$page['slug']==='home'?'/':'/'.$page['slug'].'/';
        foreach($menu['items'] as $item)if(rtrim($item['url'],'/')===rtrim($url,'/'))throw new \DomainException('Page déjà présente.');
        $items=$menu['items'];$items[]=['id'=>'page-'.substr(hash('sha256',$pageId),0,16),'label'=>$page['values']['title'],'url'=>$url,'visible'=>true];
        $this->navigation->save($actor,$menuId,$menu['label'],$menu['location'],$items,$version);
    }
    public function transitionPage(ActorContext $actor,string $id,int $version,string $target):void
    {
        $this->page($actor,$id);
        if(!in_array($target,['review','draft','archived'],true))throw new \InvalidArgumentException('Transition invalide.');
        $this->content->transition($actor,$id,$target,$version,bin2hex(random_bytes(16)));
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
