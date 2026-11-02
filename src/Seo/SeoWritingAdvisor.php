<?php
declare(strict_types=1);
namespace Dendrila\Cms\Seo;

/** Lightweight factual editorial checks; never rewrites copy or calls an AI. */
final class SeoWritingAdvisor
{
    private static function stringify(mixed $node, array &$parts, int $level=0):void
    {
        if($level>8||count($parts)>800)return;
        if(!is_array($node))return;
        foreach($node as $key=>$value){
            // URLs, IDs and configuration are not human-visible prose.
            if(in_array($key,['url','href','slug','media_id','media_ids','provider','items_count','type','icon','id','image_url'],true))continue;
            if(is_string($value)&&in_array($key,['title','heading','text','lead','intro','description','label','value','alt','caption','answer','question'],true)){
                $parts[]=trim(strip_tags(html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8')));
            }elseif(is_array($value))self::stringify($value,$parts,$level+1);
        }
    }
    public static function pageText(array $page):string
    {
        $parts=[];self::stringify($page,$parts);
        return trim(implode(' ',$parts));
    }
    public static function contains(string $haystack,string $word):bool
    {
        return trim($word)!=='' && mb_stripos($haystack,$word,0,'UTF-8')!==false;
    }

    /** @return array{checks:list<array{status:string,label:string,detail:string}>,total:int} */
    public static function review(array $page,array $metadata,array $keywords):array
    {
        $title=(string)($metadata['title']?:($page['title']??''));
        $description=trim((string)($metadata['description']??''));
        $body=self::pageText(['blocks'=>$page['blocks']??[]]);
        $terms=SeoKeywordSettings::normalize($keywords,5);
        $checks=[];
        $add=static function(string $status,string $label,string $detail)use(&$checks):void{
            $checks[]=['status'=>$status,'label'=>$label,'detail'=>$detail];
        };
        $count=mb_strlen($title);
        if($count<25)$add('suggestion','Un titre plus précis','Décrivez le sujet et la spécificité de la page. Évitez un titre générique.');
        elseif($count>65)$add('suggestion','Titre assez long','Vérifiez que les mots importants sont lisibles au début du titre.');
        else $add('ok','Titre informatif','La longueur est raisonnable ; la pertinence reste à apprécier à la lecture.');
        $len=mb_strlen($description);
        if($len===0)$add('suggestion','Ajouter une description','Résumez en une ou deux phrases ce que cette page apporte au visiteur.');
        elseif($len<70)$add('suggestion','Description très courte','Complétez-la avec l’information qui distingue cette page.');
        elseif($len>170)$add('suggestion','Description longue','Raccourcissez si nécessaire : l’affichage varie selon la recherche et l’appareil.');
        else $add('ok','Description renseignée','Elle est de longueur raisonnable. Elle doit rester spécifique à cette page.');
        if(mb_strlen(trim($body))<140)
            $add('suggestion','Contenu peu détaillé','Ajoutez des informations concrètes et utiles, plutôt que des mots-clés répétés.');
        else $add('ok','Contenu présent','Vérifiez qu’il répond réellement aux questions de vos visiteurs.');
        $headings=[];
        foreach($page['blocks']??[] as $block){
            if(!is_array($block))continue;
            if(is_string($block['heading']??null)&&trim($block['heading'])!=='')$headings[]=$block['heading'];
        }
        if(count($page['blocks']??[])>=3 && count($headings)===0)
            $add('suggestion','Structurer la page','Utilisez des intertitres parlants pour faciliter le parcours.');
        $links=0;$missingAlt=0;
        foreach($page['blocks']??[] as $block){
            if(!is_array($block))continue;
            if(is_string($block['url']??null)&&str_starts_with($block['url'],'/')&&!str_starts_with($block['url'],'//'))++$links;
            if(($block['type']??null)==='image' && trim((string)($block['alt']??''))==='')++$missingAlt;
            foreach($block['items']??[] as $item){
                if(is_array($item)&&is_string($item['url']??null)
                    && str_starts_with($item['url'],'/')&&!str_starts_with($item['url'],'//'))++$links;
            }
        }
        if($links===0)$add('suggestion','Explorer les liens internes','Ajoutez un lien utile vers une autre page pertinente, si le contenu le permet.');
        if($missingAlt>0)$add('suggestion','Images à décrire','Prévoyez un texte alternatif pour les images informatives (laissez vide pour les images décoratives).');
        if($terms===[])$add('info','Choisir un sujet principal','Sélectionnez les expressions qui correspondent réellement à cette page.');
        foreach($terms as $term){
            if(!self::contains($title,$term))
                $add('suggestion','Sujet dans le titre : '.$term,'Si ce sujet est central, pensez à l’exprimer naturellement dans le titre.');
            if(!self::contains($body,$term))
                $add('suggestion','Sujet dans le contenu : '.$term,'Le sujet choisi apparaît peu ou pas dans le texte visible. Ajustez le sujet ou le contenu, sans répétition forcée.');
            elseif(self::contains($title,$term))
                $add('ok','Sujet cohérent : '.$term,'L’expression est présente dans le titre et le contenu.');
        }
        return ['checks'=>$checks,'total'=>count($checks)];
    }
}
