<?php
declare(strict_types=1);
namespace Dendrila\Cms\BackOffice;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Media\MediaService;
use Dendrila\Cms\Http\UploadedFile;
final readonly class NativeMediaPort implements MediaPort
{
    public function __construct(private MediaService $media){}
    public function list(ActorContext $actor):array {
        $module=$this->media->module($actor,'media_library');$rows=[];$offset=0;
        do{$result=$this->media->search($actor,$module,['limit'=>100,'offset'=>$offset,'sort'=>'title','direction'=>'asc']);
            foreach($result['items'] as $item)$rows[]=['id'=>$item['id'],'title'=>$item['title']??'','alt'=>$item['alt']??'','preview_url'=>$item['thumb']??$item['path']??''];
            $offset+=100;
        }while($result['has_more'] && $offset<1000);
        return $rows;
    }
    public function upload(ActorContext $actor,array $file,string $title,string $alt):void {
        $this->media->upload($actor,['module_id'=>'media_library','item_id'=>'media_library','client_request_id'=>bin2hex(random_bytes(16)),
            'metadata'=>json_encode(['title'=>$title,'alt'=>$alt],JSON_THROW_ON_ERROR)],UploadedFile::fromPhp($file));
    }
}
