<?php
declare(strict_types=1);
namespace Dendrila\Cms\BackOffice;
/** Form mapping preserves fields and blocks not edited by this interface. */
final class BlockEditor
{
    public const FIELDS=[
        'text'=>['text'=>'Texte'],'rich_text'=>['text'=>'Texte'],
        'image'=>['media_id'=>'Photographie','alt'=>'Description accessible','caption'=>'Légende'],
        'gallery'=>['heading'=>'Titre de la galerie','alt'=>'Description de la galerie'],
        'cta'=>['label'=>'Texte du bouton','url'=>'Destination'],
        'embed_safe'=>['provider'=>'Fournisseur','url'=>'Adresse','label'=>'Libellé','heading'=>'Titre'],
        'cards'=>['heading'=>'Titre'],'faq'=>['heading'=>'Titre'],
        'key_values'=>['heading'=>'Titre'],'statistics'=>['heading'=>'Titre'],
    ];
    public const ITEMS=['cards'=>['title'=>'Titre','text'=>'Texte','url'=>'Lien','media_id'=>'Photo'],
        'faq'=>['question'=>'Question','answer'=>'Réponse'],
        'key_values'=>['label'=>'Libellé','value'=>'Valeur'],
        'statistics'=>['label'=>'Libellé','value'=>'Valeur','note'=>'Précision']];
    public static function update(array $original,array $edits,array $allowedMedia):array {
        foreach($edits as $index=>$edit){
            if(!ctype_digit((string)$index)||!isset($original[(int)$index])||!is_array($edit))throw new \InvalidArgumentException('Bloc invalide.');
            $block=$original[(int)$index];$fields=self::FIELDS[$block['type']]??[];
            if(array_diff(array_keys($edit),[...array_keys($fields),'media_ids','items'])!==[])throw new \InvalidArgumentException('Champ de bloc inconnu.');
            foreach($fields as $key=>$label){if(!array_key_exists($key,$edit))continue;
                if(!is_string($edit[$key])||strlen($edit[$key])>($key==='text'?100000:2000))throw new \InvalidArgumentException('Champ de bloc invalide.');
                if($key==='media_id'&&!in_array($edit[$key],$allowedMedia,true))throw new \InvalidArgumentException('Photo inconnue.');
                if($key==='url'&&$edit[$key]!=='')self::link($edit[$key]);
                if($key==='provider'&&!in_array($edit[$key],['youtube-nocookie','vimeo','openstreetmap'],true))throw new \InvalidArgumentException('Fournisseur non pris en charge.');
                $block[$key]=$edit[$key];
            }
            if(isset($edit['media_ids'])){
                if($block['type']!=='gallery'||!is_array($edit['media_ids'])||!array_is_list($edit['media_ids'])||count($edit['media_ids'])>60)throw new \InvalidArgumentException('Galerie invalide.');
                $ids=array_values(array_filter($edit['media_ids'],static fn($v)=>$v!==''));
                foreach($ids as $id)if(!is_string($id)||!in_array($id,$allowedMedia,true))throw new \InvalidArgumentException('Photo inconnue.');
                $block['media_ids']=array_values(array_unique($ids));
            }
            if(isset($edit['items'])){
                if(!isset(self::ITEMS[$block['type']])||!is_array($edit['items']))throw new \InvalidArgumentException('Éléments invalides.');
                foreach($edit['items'] as $i=>$item){
                    if(!ctype_digit((string)$i)||!isset($block['items'][(int)$i])||!is_array($item)||array_diff(array_keys($item),array_keys(self::ITEMS[$block['type']]))!==[])throw new \InvalidArgumentException('Élément inconnu.');
                    foreach($item as $key=>$value){if(!is_string($value)||strlen($value)>100000)throw new \InvalidArgumentException('Valeur invalide.');
                        if(in_array($key,['url','media_id'],true)&&$value===''){unset($block['items'][$i][$key]);continue;}
                        if($key==='media_id'&&!in_array($value,$allowedMedia,true))throw new \InvalidArgumentException('Photo inconnue.');
                        if($key==='url')self::link($value);
                        $block['items'][$i][$key]=$value;
                    }
                }
            }
            if($block['type']==='embed_safe'&&($block['url']??'')!=='')self::embed($block);
            $original[(int)$index]=$block;
        }
        return $original;
    }
    private static function link(string $url):void {
        if(preg_match('/[\\\\\x00-\x20]/',$url)||str_starts_with($url,'//'))throw new \InvalidArgumentException('Adresse de lien invalide.');
        if(str_starts_with($url,'/')||str_starts_with($url,'#'))return;
        if(str_starts_with($url,'mailto:')&&filter_var(substr($url,7),FILTER_VALIDATE_EMAIL))return;
        if(filter_var($url,FILTER_VALIDATE_URL)&&parse_url($url,PHP_URL_SCHEME)==='https')return;
        throw new \InvalidArgumentException('Utilisez un lien HTTPS, une adresse du site ou une adresse e-mail.');
    }
    private static function embed(array $block):void {
        $hosts=['youtube-nocookie'=>['www.youtube-nocookie.com','youtube-nocookie.com'],'vimeo'=>['player.vimeo.com'],'openstreetmap'=>['www.openstreetmap.org','openstreetmap.org']];
        $url=$block['url'];
        if(parse_url($url,PHP_URL_SCHEME)!=='https'||!in_array(strtolower(parse_url($url,PHP_URL_HOST)??''),$hosts[$block['provider']]??[],true)||parse_url($url,PHP_URL_USER)!==null)
            throw new \InvalidArgumentException('Utilisez l’adresse HTTPS d’intégration du fournisseur choisi.');
    }
    public static function collection(array $block,string $operation,int $index):array {
        if(!isset(self::ITEMS[$block['type']])||!is_array($block['items']??null))throw new \InvalidArgumentException('Collection inconnue.');
        $items=$block['items'];
        if($operation==='add_item'){
            if(count($items)>=50)throw new \InvalidArgumentException('Une collection contient au maximum 50 éléments.');
            $item=array_fill_keys(array_keys(self::ITEMS[$block['type']]),'');unset($item['media_id'],$item['url']);$items[]=$item;
        }else{
            if(!isset($items[$index]))throw new \InvalidArgumentException('Élément introuvable.');
            if($operation==='remove_item')array_splice($items,$index,1);
            elseif($operation==='item_up'&&$index>0)[$items[$index-1],$items[$index]]=[$items[$index],$items[$index-1]];
            elseif($operation==='item_down'&&$index<count($items)-1)[$items[$index+1],$items[$index]]=[$items[$index],$items[$index+1]];
            else throw new \InvalidArgumentException('Déplacement impossible.');
        }
        $block['items']=array_values($items);return $block;
    }
    public static function blank(string $type):array {
        return match($type){'text','rich_text'=>['type'=>$type,'text'=>''], 'gallery'=>['type'=>'gallery','media_ids'=>[]],
            'embed_safe'=>['type'=>'embed_safe','provider'=>'youtube-nocookie','url'=>'','label'=>'','heading'=>''],
            'cta'=>['type'=>'cta','label'=>'En savoir plus','url'=>'/'],
            'cards','faq','key_values','statistics'=>['type'=>$type,'heading'=>'','items'=>[]],
            default=>throw new \InvalidArgumentException('Type de bloc non disponible.')};
    }
}
