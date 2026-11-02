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
            'media'=>['label'=>'Photos et galeries','description'=>'Photothèque, images optimisées et galeries dans vos pages.','requires'=>['gd','fileinfo'],'database'=>false],
            'forum'=>['label'=>'Forum modéré','description'=>'Discussions et réponses modérées, avec stockage local SQLite.','requires'=>['gd','fileinfo'],'database'=>false],
            'booking'=>['label'=>'Réservations et paiements — Booking',
                'description'=>'Préparer Booking pour une activation ultérieure. Stockage SQLite privé pour les rendez-vous, paiements ponctuels ou les deux. Activation différée jusqu’aux tests.',
                'requires'=>[],'database'=>false,'setup_pending'=>true],
        ];
    }
    public static function bookingModes():array {
        return [
            'appointments'=>['label'=>'Prise de rendez-vous','description'=>'Créneaux, disponibilités et réservations, avec acompte ou paiement facultatif.'],
            'payments'=>['label'=>'Paiements ponctuels','description'=>'Paiement d’une prestation unique sans créneau ni calendrier.'],
            'both'=>['label'=>'Les deux','description'=>'Rendez-vous et paiements ponctuels indépendants.'],
        ];
    }
    public static function bookingMode(mixed $mode,bool $requested):string {
        if(!$requested){
            if($mode!==null&&$mode!=='none')throw new \InvalidArgumentException('Mode Booking sans module sélectionné.');
            return 'none';
        }
        if(!is_string($mode)||!isset(self::bookingModes()[$mode]))
            throw new \InvalidArgumentException('Choisissez le type de Booking.');
        return $mode;
    }
    public static function selection(mixed $ids):array {
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>count(self::addons()))throw new \InvalidArgumentException('Sélection de modules invalide.');
        foreach($ids as $id)if(!is_string($id)||!isset(self::addons()[$id]))throw new \InvalidArgumentException('Module inconnu.');
        return array_values(array_unique($ids));
    }
    public static function requirements(array $ids):array {
        $ids=self::selection($ids);$extensions=['pdo_sqlite','mbstring','sodium'];
        foreach($ids as $id) $extensions=array_merge($extensions,self::addons()[$id]['requires']);
        $reasons=['Les contenus, les comptes et les modules sélectionnés utilisent un fichier SQLite privé créé automatiquement.'];
        if(in_array('booking',$ids,true))$reasons[]='Booking utilisera un stockage SQLite privé, après adaptation et tests du moteur.';
        return [
            'database'=>false,'driver'=>'sqlite',
            'database_reasons'=>$reasons,
            'extensions'=>array_values(array_unique($extensions)),
        ];
    }
}
