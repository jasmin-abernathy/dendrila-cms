<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;

use Dendrila\Cms\Http\UploadedFile;

/** Private first-party font storage. Signatures, lengths and extensions checked. */
final class BrandFontStore
{
    public const FORMATS=[
        'woff2'=>['signature'=>"wOF2",'mime'=>'font/woff2'],
        'woff'=>['signature'=>"wOFF",'mime'=>'font/woff'],
        'truetype'=>['signature'=>"\x00\x01\x00\x00",'mime'=>'font/ttf'],
        'opentype'=>['signature'=>"OTTO",'mime'=>'font/otf'],
    ];
    private static function directory(string $site, bool $create): string
    {
        if(!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$site))throw new \InvalidArgumentException('Invalid font site.');
        $base=dirname(__DIR__,2).'/storage';
        if(!is_dir($base)||is_link($base))throw new \RuntimeException('Private storage unavailable.');
        $parent=$base.'/brand-fonts';$folder=$parent.'/'.$site;
        if($create)foreach([$parent,$folder] as $dir){
            if(!is_dir($dir)&&!mkdir($dir,0700)&&!is_dir($dir))
                throw new \RuntimeException('Cannot create font storage.');
        }
        if(is_link($parent)||is_link($folder))throw new \RuntimeException('Private font symlink prohibited.');
        return $folder;
    }
    /** @return string format:token */
    public static function upload(string $site,UploadedFile $file):string
    {
        if($file->error!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Le transfert de la police a échoué.');
        if(!is_file($file->path)||is_link($file->path))throw new \InvalidArgumentException('Fichier temporaire invalide.');
        $size=filesize($file->path);
        if($size===false||$size<48||$size>3_000_000)
            throw new \InvalidArgumentException('Police vide ou trop volumineuse (3 Mo maximum).');
        $head=file_get_contents($file->path,false,null,0,48);
        if(!is_string($head)||strlen($head)<48)throw new \InvalidArgumentException('Police tronquée.');
        $ext=strtolower(pathinfo($file->name,PATHINFO_EXTENSION));
        $formats=['woff2'=>'woff2','woff'=>'woff','ttf'=>'truetype','otf'=>'opentype'];
        $format=$formats[$ext]??null;
        if($format===null||substr($head,0,4)!==self::FORMATS[$format]['signature'])
            throw new \InvalidArgumentException('Police non reconnue. Utilisez WOFF2, WOFF, TTF ou OTF.');
        if(in_array($format,['woff2','woff'],true)){
            $declared=unpack('N',substr($head,8,4))[1];
            $tables=unpack('n',substr($head,12,2))[1];
            if($declared!==$size||$tables<1||$tables>4096)
                throw new \InvalidArgumentException('En-tête de police invalide.');
        }else{
            $tables=unpack('n',substr($head,4,2))[1];
            if($tables<1||$tables>4096)
                throw new \InvalidArgumentException('Table de police invalide.');
        }
        $folder=self::directory($site,true);
        $token=bin2hex(random_bytes(24));
        $destination=$folder.'/'.$token.'.'.$format;
        if(!copy($file->path,$destination)||!chmod($destination,0600)){
            @unlink($destination);throw new \RuntimeException('Impossible de conserver la police.');
        }
        return $format.':'.$token;
    }
    public static function file(string $site,string $ref):?array
    {
        if(!preg_match('/^(woff2|woff|truetype|opentype):([a-f0-9]{48})$/D',$ref,$m))return null;
        $folder=self::directory($site,false);
        if(!is_dir($folder))return null;
        $path=$folder.'/'.$m[2].'.'.$m[1];
        if(!is_file($path)||is_link($path))return null;
        return ['path'=>$path,'mime'=>self::FORMATS[$m[1]]['mime']];
    }
    public static function discard(string $site,string $ref):void
    {
        $asset=self::file($site,$ref);
        if($asset!==null)@unlink($asset['path']);
    }
}
