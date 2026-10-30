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
                        $block['items'][$i][$key]=$value;
                    }
                }
            }
            $original[(int)$index]=$block;
        }
        return $original;
    }
    public static function blank(string $type):array {
        return match($type){'text'=>['type'=>'text','text'=>''], 'gallery'=>['type'=>'gallery','media_ids'=>[]],
            'cta'=>['type'=>'cta','label'=>'En savoir plus','url'=>'/'],
            'cards','faq','key_values','statistics'=>['type'=>$type,'heading'=>'','items'=>[]],
            default=>throw new \InvalidArgumentException('Type de bloc non disponible.')};
    }
}
