<?php
declare(strict_types=1);
namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Storage\PdoCmsStore;

/**
 * Shared, opt-in-capability native pages editor for the MMW v1 records UI.
 * Mutations reuse the CMS content workflow, permissions and version checks.
 */
final class NativePagesModule
{
    public const ID='pages';

    public static function definition(bool $writable,bool $publish): array
    {
        return [
            'id'=>self::ID,
            'kind'=>'records',
            'title'=>'Pages du site',
            'subtitle'=>'Ouvrez et modifiez les textes de vos pages ; les modifications restent des brouillons jusqu’à publication.',
            'icon'=>'file-text',
            'order'=>20,
            'writable'=>$writable,
            'fields'=>[
                ['id'=>'title','type'=>'text','label'=>'Titre','required'=>true,'max_length'=>240],
                ['id'=>'body','type'=>'multiline','label'=>'Texte du premier bloc','max_length'=>20000,
                 'hint'=>'Seul le premier bloc de texte est modifié. Les photos, galeries et autres blocs restent intacts.'],
            ],
            'options'=>[
                'allow_create'=>false,
                'allow_update'=>$writable,
                'allow_delete'=>false,
                'require_version'=>true,
                'save_label'=>'Enregistrer le brouillon',
                'group_id'=>'site',
                'group_title'=>'Contenus du site',
                'group_order'=>20,
                'item_label'=>'une page',
            ],
            'actions'=>$publish?[[
                'id'=>'publish',
                'label'=>'Publier cette page',
                'tone'=>'primary',
                'requires_confirmation'=>true,
                'confirmation_text'=>'Publier cette version de la page sur le site ?',
                'allow_offline'=>false,
            ]]:[],
        ];
    }

    /** @param list<array<string,mixed>> $storedModules */
    public static function assertReservedIdAvailable(array $storedModules): void
    {
        foreach($storedModules as $module) {
            if(($module['id']??null)===self::ID)
                throw new \RuntimeException('The pages module id is reserved by Dendrila CMS.');
        }
    }

    public static function snapshot(ContentService $content,ActorContext $actor): array
    {
        $items=[];
        for($offset=0;$offset<100;$offset+=50) {
            $batch=$content->list($actor,['type'=>'page','limit'=>50,'offset'=>$offset]);
            foreach($batch as $page) {
                $values=$page['values']??[];
                $body='';
                foreach(($values['blocks']??[]) as $block){
                    if(($block['type']??null)==='text'){
                        $body=(string)($block['text']??'');
                        break;
                    }
                }
                $items[]=[
                    'id'=>(string)($page['content_id']??''),
                    'title'=>(string)($values['title']??$page['slug']??''),
                    'subtitle'=>(string)($page['slug']??''),
                    'body'=>$body,
                    'status'=>(string)($page['status']??''),
                    'version'=>(int)($page['version']??0),
                ];
            }
            if(count($batch)<50)break;
        }
        return ['items'=>$items];
    }

    public static function apply(ContentService $content,ActorContext $actor,string $action,array $payload,string $requestId): array
    {
        $id=$payload['item_id']??null;
        $version=$payload['expected_version']??null;
        if(!is_string($id)||preg_match('/^[a-f0-9]{32}$/D',$id)!==1 ||
            !is_int($version) || $version<1) {
            throw new \InvalidArgumentException('Page id and current version are required.');
        }
        $page=$content->get($actor,$id);
        if(($page['type_id']??null)!=='page')throw new ContentNotFound('Page not found.');
        if($action==='update_item'){
            if(array_diff(array_keys($payload),['item_id','expected_version','title','body'])!==[]
                || !is_string($payload['title']??null)||!is_string($payload['body']??null)){
                throw new \InvalidArgumentException('Invalid editable page fields.');
            }
            $title=trim($payload['title']);
            $body=$payload['body'];
            if($title===''||mb_strlen($title)>240||mb_strlen($body)>20000){
                throw new \InvalidArgumentException('Page title or body is invalid.');
            }
            $values=$page['values'];
            $values['title']=$title;
            $found=false;
            foreach($values['blocks'] as &$block){
                if(($block['type']??null)==='text'){
                    $block['text']=$body;$found=true;break;
                }
            }
            unset($block);
            if(!$found && $body!=='') $values['blocks'][]=['type'=>'text','text'=>$body];
            $result=$content->updateDraft($actor,$id,$version,$values,$requestId);
            return ['status'=>'applied','request_id'=>$requestId,
                    'message'=>'Brouillon de page enregistré. Vous pouvez le publier depuis le site ou l’application.',
                    'result'=>$result['content']??null];
        }
        if($action==='item_action' && ($payload['action_id']??null)==='publish'
            && array_diff(array_keys($payload),['item_id','expected_version','action_id'])===[]){
            $result=$content->transition($actor,$id,'published',$version,$requestId);
            return ['status'=>$result['status']??'applied','request_id'=>$requestId,
                'message'=>($result['status']??'')==='pending_review'
                    ?'Page envoyée pour validation.':'Page publiée.',
                'result'=>$result['content']??null];
        }
        throw new AccessDenied('This page action is not enabled.');
    }
}
