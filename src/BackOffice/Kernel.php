<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Authorization\AccessDenied;

/**
 * Front HTTP commun : aucun mot de passe, cookie d'identité ou jeton MMW créé ici.
 * Les adaptateurs existants du site délivrent un ActorContext après vraie 2FA.
 */
final readonly class Kernel
{
    public function __construct(
        private Config $config,
        private IdentityProvider $identity,
        private CsrfProvider $csrf,
        private CoreData $core,
        private ?MediaPort $media = null,
        private ?UsersPort $users = null,
    ) {}

    /** @param array<string,mixed> $get @param array<string,mixed> $post @param array<string,mixed> $files */
    public function handle(string $method, array $get = [], array $post = [], array $files = []): Result
    {
        $headers = [
            'Cache-Control'=>'private, no-store, max-age=0',
            'Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff',
            'X-Frame-Options'=>'DENY',
            'Referrer-Policy'=>'no-referrer',
            'Vary'=>'Cookie',
            'Content-Security-Policy'=>"default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; manifest-src 'self'; connect-src 'self'",
        ];
        if ($method==='GET' && ($get['theme']??null)==='1') {
            return new Result(200, ':root{--admin-accent:'.$this->config->primaryColor.'}', [
                'Content-Type'=>'text/css; charset=utf-8',
                'Cache-Control'=>'public, max-age=3600',
                'X-Content-Type-Options'=>'nosniff',
            ]);
        }
        if ($method==='GET' && ($get['manifest']??null)==='1') {
            return new Result(200,$this->manifest(),[
                'Content-Type'=>'application/manifest+json; charset=utf-8',
                'Cache-Control'=>'public, max-age=3600','X-Content-Type-Options'=>'nosniff',
            ]);
        }
        $actor=$this->identity->currentActor();
        if (!$actor || $actor->siteId!==$this->config->siteId) {
            return new Result(401,View::unauthorized($this->config),$headers+['Content-Type'=>'text/html; charset=utf-8']);
        }
        if ($method!=='GET' && $method!=='POST') {
            return new Result(405,'Méthode non autorisée.',$headers+['Allow'=>'GET, POST']);
        }
        $section=is_string($get['section']??null)?$get['section']:'textes';
        if (!in_array($section,['tableau','textes','menu','medias','utilisateurs','appli'],true)) $section='tableau';
        if ($method==='POST') {
            if (!$this->csrf->verify(is_string($post['csrf']??null)?$post['csrf']:'')) {
                return new Result(403,'Formulaire expiré. Rechargez la page.',$headers);
            }
            try {
                $redirect=$this->write($actor,$post,$files);
                return new Result(303,'',$headers+['Location'=>$this->config->url($redirect['section'],$redirect['params'])]);
            } catch (AccessDenied $e) {
                return new Result(403,'Action interdite.',$headers);
            } catch (VersionConflict $e) {
                return new Result(409,View::error($this->config,'Le contenu a été modifié ailleurs. Rechargez avant d’enregistrer.'),$headers+['Content-Type'=>'text/html; charset=utf-8']);
            } catch (\InvalidArgumentException|\DomainException|ContentNotFound $e) {
                return new Result(422,View::error($this->config,'Impossible d’enregistrer : informations manquantes ou invalides.'),$headers+['Content-Type'=>'text/html; charset=utf-8']);
            }
        }
        try {
            $pageId=is_string($get['page']??null)?$get['page']:'';
            $menuId=is_string($get['menu']??null)?$get['menu']:'';
            $pages=$this->core->pages($actor);
            $menus=$this->core->menus($actor);
            $selectedPage=$pages===[]?null:$pages[0];
            if($pageId!=='') {
                foreach($pages as $item) {
                    if(($item['content_id']??null)===$pageId){$selectedPage=$item;break;}
                }
            }
            $selectedMenu=$menus===[]?null:$menus[0];
            if($menuId!=='') {
                foreach($menus as $menu) {
                    if($menu['menu_id']===$menuId){$selectedMenu=$menu;break;}
                }
            }
            $view=new View($this->config,$this->csrf->token(),$this->core,$this->media,$this->users);
            return new Result(200,$view->render($actor,$section,$pages,$selectedPage,$menus,$selectedMenu,$get),$headers+['Content-Type'=>'text/html; charset=utf-8']);
        } catch (AccessDenied $e) {
            return new Result(403,View::error($this->config,'Vous ne disposez pas des droits nécessaires.'),$headers+['Content-Type'=>'text/html; charset=utf-8']);
        }
    }

    /**
     * @param array<string,mixed> $post @param array<string,mixed> $files
     * @return array{section:string,params:array<string,string>}
     */
    private function write(ActorContext $actor,array $post,array $files): array
    {
        $action=(string)($post['action']??'');
        if($action==='save_page' || $action==='publish_page') {
            $id=self::scalar($post,'page_id',128);
            $version=self::positiveInt($post,'version');
            if($action==='save_page') {
                $formFields=$post['fields']??null;
                if(!is_array($formFields) || count($formFields)>40)throw new \InvalidArgumentException('Champs invalides.');
                $allowed=array_column($this->core->textFields($actor),'id');
                $fields=[];
                foreach($formFields as $key=>$value) {
                    if(!is_string($key)||!in_array($key,$allowed,true)||!is_string($value)||strlen($value)>100000)
                        throw new \InvalidArgumentException('Champ invalide.');
                    $fields[$key]=$value;
                }
                $this->core->savePage($actor,$id,$version,$fields);
            } else {
                $this->core->publishPage($actor,$id,$version);
            }
            return ['section'=>'textes','params'=>['page'=>$id,'saved'=>'1']];
        }
        if($action==='create_page') {
            $template=self::scalar($post,'template',30);
            if($template==='gallery'&&!$this->media)throw new \InvalidArgumentException('Activez la médiathèque avant de créer une galerie.');
            $title=self::scalar($post,'title',240);
            $slug=self::scalar($post,'slug',80);
            $id=$this->core->createPage($actor,$template,$title,$slug);
            return ['section'=>'textes','params'=>['page'=>$id,'created'=>'1']];
        }
        if($action==='save_gallery_photos') {
            if(!$this->media || !$this->core->can($actor,'media.read'))throw new AccessDenied();
            $id=self::scalar($post,'page_id',128);
            $version=self::positiveInt($post,'version');
            $selected=$post['media_ids']??[];
            if(!is_array($selected) || !array_is_list($selected) ||
                count($selected)>60 || count(array_filter($selected,'is_string'))!==count($selected))
                throw new \InvalidArgumentException('Sélection de médias invalide.');
            $valid=array_column($this->media->list($actor),'id');
            if(array_diff($selected,$valid)!==[])
                throw new \InvalidArgumentException('Une photographie n’appartient pas à cette photothèque.');
            $this->core->saveGalleryPhotos($actor,$id,$version,$selected);
            return ['section'=>'textes','params'=>['page'=>$id,'saved'=>'1']];
        }
        if(in_array($action,['save_blocks','change_block','transition_page','add_page_to_menu'],true)) {
            $id=self::scalar($post,'page_id',128);$version=self::positiveInt($post,'version');
            if($action==='save_blocks') {
                $edits=$post['blocks']??[];if(!is_array($edits)||count($edits)>200)throw new \InvalidArgumentException('Blocs invalides.');
                $mediaIds=$this->media&&$this->core->can($actor,'media.read')?array_column($this->media->list($actor),'id'):[];
                $this->core->saveBlocks($actor,$id,$version,$edits,$mediaIds);
            }elseif($action==='change_block') {
                $op=self::scalar($post,'operation',20);$index=filter_var($post['index']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
                if($index===false)throw new \InvalidArgumentException('Bloc invalide.');
                $type=is_string($post['type']??null)?$post['type']:'text';$mediaId=is_string($post['media_id']??null)?$post['media_id']:'';
                if($op==='add'&&$type==='gallery'&&!$this->media)throw new \InvalidArgumentException('La médiathèque est nécessaire pour ajouter une galerie.');
                if($op==='add'&&$type==='image'&&(!$this->media||!in_array($mediaId,array_column($this->media->list($actor),'id'),true)))throw new \InvalidArgumentException('Choisissez une photo.');
                $itemIndex=filter_var($post['item_index']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
                if($itemIndex===false)throw new \InvalidArgumentException('Élément invalide.');
                $initial=$post['initial']??[];if(!is_array($initial))throw new \InvalidArgumentException('Options invalides.');
                $this->core->changeBlock($actor,$id,$version,$op,$index,$type,$mediaId,$itemIndex,$initial);
            }elseif($action==='transition_page') {
                $this->core->transitionPage($actor,$id,$version,self::scalar($post,'target',20));
            }else $this->core->addPageToMenu($actor,$id,self::scalar($post,'menu_id',64),$version);
            return ['section'=>$action==='add_page_to_menu'?'menu':'textes','params'=>['page'=>$id,'saved'=>'1']];
        }
        if($action==='save_menu') {
            $id=self::scalar($post,'menu_id',64);
            $version=self::positiveInt($post,'version');
            $order=$post['order']??null;
            $labels=$post['labels']??null;
            $visible=$post['shown']??[];
            if(!is_array($order)||!array_is_list($order)||!is_array($labels)||!is_array($visible)
                || count($order)>50||count($labels)>50||count($visible)>50
                || count(array_filter($labels,'is_string'))!==count($labels)
                || count(array_filter($order,'is_string'))!==count($order))
                throw new \InvalidArgumentException('Menu invalide.');
            $this->core->reorderMenu($actor,$id,$version,$order,$labels,$visible);
            return ['section'=>'menu','params'=>['menu'=>$id,'saved'=>'1']];
        }
        if($action==='upload_media') {
            if(!$this->media || !$this->core->can($actor,'media.upload'))throw new AccessDenied();
            $file=$files['image']??null;
            $title=self::scalar($post,'title',200);
            $alt=self::scalar($post,'alt',500);
            if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK
                || !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name'])
                || (int)($file['size']??0)<1 || (int)$file['size']>6*1024*1024)
                throw new \InvalidArgumentException('Fichier invalide.');
            $this->media->upload($actor,$file,$title,$alt);
            return ['section'=>'medias','params'=>['saved'=>'1']];
        }
        if($action==='invite_user') {
            if(!$this->users || !$this->core->can($actor,'users.manage'))throw new AccessDenied();
            $email=self::scalar($post,'email',254);
            $role=self::scalar($post,'role',20);
            if(filter_var($email,FILTER_VALIDATE_EMAIL)===false || !in_array($role,['admin','editor','contributor','viewer'],true))
                throw new \InvalidArgumentException('Invitation invalide.');
            $this->users->invite($actor,$email,$role);
            return ['section'=>'utilisateurs','params'=>['saved'=>'1']];
        }
        throw new \InvalidArgumentException('Action inconnue.');
    }

    /** @param array<string,mixed> $from */
    private static function scalar(array $from,string $key,int $maxLength): string
    {
        $value=$from[$key]??null;
        if(!is_string($value) || strlen($value)>$maxLength || trim($value)==='')throw new \InvalidArgumentException('Champ invalide.');
        return trim($value);
    }
    /** @param array<string,mixed> $from */
    private static function positiveInt(array $from,string $key): int
    {
        $value=filter_var($from[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($value===false)throw new \InvalidArgumentException('Version invalide.');
        return $value;
    }
    private function manifest(): string
    {
        return json_encode([
            'id'=>$this->config->basePath,
            'name'=>$this->config->siteName.' — Administration',
            'short_name'=>'Administration',
            'start_url'=>$this->config->url('textes'),
            'scope'=>$this->config->basePath,
            'display'=>'standalone',
            'background_color'=>'#f7f6f2',
            'theme_color'=>$this->config->primaryColor,
            'icons'=>[['src'=>$this->config->iconUrl,'sizes'=>'any']],
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
