<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;
final class Catalog
{
    public static function themes():array {
        $themes=[];
        foreach(glob(dirname(__DIR__,2).'/design-styles/*/style.json')?:[] as $file){
            $data=json_decode((string)file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
            new \Dendrila\Cms\Design\DesignStyleDefinition($data);
            $themes[$data['id']]=$data;
        }
        return $themes;
    }
    public static function addons():array {
        return [
            'media'=>['label'=>'Photos et galeries','description'=>'Photothèque, images optimisées et galeries dans vos pages.','requires'=>['gd','fileinfo'],'database'=>true],
            'forum'=>['label'=>'Forum modéré','description'=>'Discussions et réponses de membres invités, publiées après validation.','requires'=>['gd','fileinfo'],'database'=>true],
        ];
    }
    public static function selection(mixed $ids):array {
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>count(self::addons()))throw new \InvalidArgumentException('Sélection de modules invalide.');
        foreach($ids as $id)if(!is_string($id)||!isset(self::addons()[$id]))throw new \InvalidArgumentException('Module inconnu.');
        return array_values(array_unique($ids));
    }
    public static function requirements(array $ids):array {
        $ids=self::selection($ids);$extensions=['pdo_mysql','mbstring','sodium'];
        $reasons=['Le noyau conserve les pages, comptes et révisions dans MariaDB.'];
        foreach($ids as $id){$extensions=array_merge($extensions,self::addons()[$id]['requires']);if(self::addons()[$id]['database'])$reasons[]=self::addons()[$id]['label'].' utilise cette même base.';}
        return ['database'=>true,'database_reasons'=>$reasons,'extensions'=>array_values(array_unique($extensions))];
    }
}
