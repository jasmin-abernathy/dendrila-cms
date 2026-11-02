<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;

/** Read-only theme examples and exact starter pages; no database writes in previews. */
final class InstallThemePreview
{
    private const STYLES = ['simple-illustrated','quiet-elegant','image-forward','ultra-minimal','graphic-rhythm','editorial'];
    private static function check(string $mode, string $style): void
    {
        if (!in_array($mode, ['empty','demo'], true) || !in_array($style, self::STYLES, true)) throw new \InvalidArgumentException('Unknown preview.');
    }
    public static function site(string $mode, string $style = 'simple-illustrated'): array
    {
        self::check($mode,$style);
        if ($mode === 'empty') return ['name'=>'Mon site'];
        return ['name'=>match($style) {
            'simple-illustrated'=>'La maison commune', 'quiet-elegant'=>'Maison Sillage',
            'image-forward'=>'Horizons', 'ultra-minimal'=>'Bureau Clair',
            'graphic-rhythm'=>'Les possibles', 'editorial'=>'Institut des territoires',
        }, 'tagline'=>'Démonstration · contenu fictif'];
    }
    public static function page(string $mode, string $style = 'simple-illustrated'): array
    {
        self::check($mode,$style);
        if ($mode === 'empty') {
            $pages=StarterSiteCatalog::installationPages($style);
            return ['title'=>$pages[0]['title'],'blocks'=>$pages[0]['blocks']];
        }
        $text=static fn(string $s):array=>['type'=>'text','text'=>$s];
        $cta=static fn(string $s):array=>['type'=>'cta','label'=>$s,'url'=>'#contenu'];
        $photo=static fn(string $id,string $alt):array=>['type'=>'image','media_id'=>$id,'alt'=>$alt];
        $cards=static fn(string $heading,array $items):array=>['type'=>'cards','heading'=>$heading,'items'=>$items];
        $gallery=static fn(array $ids):array=>['type'=>'gallery','heading'=>'Une sélection de réalisations','media_ids'=>$ids];
        $info=static fn(array $items):array=>['type'=>'key_values','heading'=>'En pratique','items'=>$items];
        return match($style) {
            'simple-illustrated'=>['title'=>'Un lieu, mille façons de participer.','blocks'=>[
                $text('Ateliers, jardin partagé et salle commune : consultez les activités et les horaires.'),
                $photo('flowers','Fleurs du jardin partagé'),$cta('Découvrir les services'),
                $cards('Comment pouvons-nous vous aider ?',[
                    ['title'=>'Trouver un atelier','text'=>'Créer, apprendre et rencontrer les habitants.'],
                    ['title'=>'Utiliser un espace','text'=>'Une salle, un établi ou un coin de jardin.'],
                    ['title'=>'Rencontrer l’équipe','text'=>'Un accueil pour donner forme à vos idées.'],
                ]),$info([['label'=>'Accueil','value'=>'Du mardi au samedi'],['label'=>'Lieu','value'=>'La maison commune'],['label'=>'Participation','value'=>'Ouverte à toutes et tous']]),
            ]],
            'quiet-elegant'=>['title'=>'Prendre le temps. Retrouver l’essentiel.','blocks'=>[
                $text('Chambres donnant sur le jardin, petit déjeuner dans la salle commune et sentiers accessibles à pied.'),
                $photo('forest','Lumière dans une forêt paisible'),$cta('Découvrir la maison'),
                $text('Les chambres donnent sur le jardin. Le petit déjeuner est servi dans la salle commune ou sur la terrasse.'),
                $gallery(['architecture','flowers']),$info([['label'=>'Le lieu','value'=>'Une maison dans la nature'],['label'=>'Accueil','value'=>'Sur rendez-vous']]),
            ]],
            'image-forward'=>['title'=>'Regarder autrement.','blocks'=>[
                $text('Paysages, bâtiments et lumières : cinq séries photographiques à parcourir.'),
                $photo('mountains','Montagnes et village au bord du lac'),$cta('Parcourir la sélection'),
                $gallery(['architecture','forest','flowers','mountains']),
                $text('Chaque série comprend des photographies, leurs légendes et une présentation du lieu.'),
            ]],
            'ultra-minimal'=>['title'=>'Votre activité avance. Votre organisation suit.','blocks'=>[
                $text('Classement, suivi des dossiers et préparation des échéances : choisissez la prestation qui correspond à votre besoin.'),
                $cta('Découvrir les prestations'),
                $cards('Des solutions concrètes',[
                    ['title'=>'Un besoin ponctuel','text'=>'Un renfort quand votre activité en a besoin.','url'=>'#contenu'],
                    ['title'=>'Un suivi régulier','text'=>'Un suivi défini avec vous, selon la fréquence nécessaire.','url'=>'#contenu'],
                ]),
                $cards('Comment pouvons-nous vous aider ?',[
                    ['title'=>'Mettre en ordre','text'=>'Rassembler les informations et retrouver des repères.'],
                    ['title'=>'Suivre les échéances','text'=>'Garder une vision simple des tâches et des dates.'],
                    ['title'=>'Renforcer une équipe','text'=>'Faire face aux périodes chargées.'],
                    ['title'=>'Préparer un changement','text'=>'Avancer étape par étape vers un nouvel outil.'],
                ]),
                $info([['label'=>'Clarté','value'=>'Des priorités lisibles'],['label'=>'Régularité','value'=>'Un suivi adapté'],['label'=>'Proximité','value'=>'Un contact identifié'],['label'=>'Souplesse','value'=>'Des missions ajustables'],['label'=>'Sérénité','value'=>'Des dossiers à jour']]),
                ['type'=>'image_text','heading'=>'Comment nous travaillons',
                 'text'=>'Le dossier est organisé par étapes, avec un récapitulatif des actions et des échéances.',
                 'image_url'=>'/design-kits/themes/very-minimal/assets/office.webp',
                 'alt'=>'Documents et fournitures organisés sur un bureau'],
                $cta('Parlons de votre projet'),
            ]],
            'graphic-rhythm'=>['title'=>'Trois jours pour fabriquer','blocks'=>[
                $text('Trois jours d’ateliers, de démonstrations et de discussions autour de la fabrication.'),$cta('Découvrir le programme'),
                ['type'=>'statistics','heading'=>'Le festival en chiffres','items'=>[
                    ['label'=>'Journées','value'=>'3','note'=>'Pour expérimenter et rencontrer'],
                    ['label'=>'Ateliers','value'=>'18','note'=>'De la découverte à la fabrication'],
                    ['label'=>'Rencontres','value'=>'9','note'=>'Conférences et tables rondes'],
                ]],
                $cards('Le programme',[
                    ['title'=>'01 / Fabriquer','text'=>'Des ateliers pour passer de l’idée au premier objet.'],
                    ['title'=>'02 / Échanger','text'=>'Des rencontres courtes, ouvertes et concrètes.'],
                    ['title'=>'03 / Célébrer','text'=>'Une soirée pour réunir les énergies.'],
                ]),
            ]],
            'editorial'=>['title'=>'Les territoires en mutation','blocks'=>[
                $text('Enquêtes de terrain, entretiens et ressources documentaires sur les transformations des territoires.'),
                $photo('architecture','Façade et composition architecturale'),$cta('Découvrir notre mission'),
                $text('Les publications présentent les résultats des enquêtes, les méthodes utilisées et les documents consultables.'),
                $info([['label'=>'Observer','value'=>'Enquêtes de terrain et diagnostics'],['label'=>'Transmettre','value'=>'Formations et ressources'],['label'=>'Accompagner','value'=>'Démarches collectives et projets locaux']]),
                ['type'=>'statistics','heading'=>'Les chiffres clés','items'=>[['value'=>'12','label'=>'territoires accompagnés'],['value'=>'24','label'=>'partenaires'],['value'=>'8','label'=>'années de terrain']]],
            ]],
        };
    }
    public static function media(string $mode): array
    {
        if (!in_array($mode,['empty','demo'],true)) throw new \InvalidArgumentException('Unknown preview.');
        return $mode==='empty' ? [] : array_combine(['mountains','forest','architecture','flowers'],array_map(static fn($id)=>'/assets/install-demo/'.$id.'.webp',['mountains','forest','architecture','flowers']));
    }
    public static function alternatives(): array
    {
        return ['mountains'=>'Montagnes et lac','forest'=>'Lumière dans la forêt','architecture'=>'Détails d’architecture','flowers'=>'Fleurs du jardin'];
    }
    public static function navigation(string $mode, string $style = 'simple-illustrated'): array
    {
        self::check($mode,$style);
        if($mode==='empty') return array_map(
            static fn(array $item):array=>[
                'label'=>$item['label'],'url'=>'/theme-preview.php?theme='.rawurlencode($style)
                    .'&mode=empty&page='.rawurlencode($item['id'])
            ],
            StarterSiteCatalog::navigation($style)
        );
        $labels=match($style){
            'simple-illustrated'=>['Services','À propos','Contact'],'quiet-elegant'=>['Présentation','Espaces','Contact'],
            'image-forward'=>['Galerie','Projets','Contact'],'ultra-minimal'=>['Services','Méthode','Contact'],
            'graphic-rhythm'=>['Agenda','À propos','Infos pratiques'],'editorial'=>['Publications','Dossiers','Contact'],
        };
        return array_map(static fn($label)=>['label'=>$label,'url'=>'#contenu'],$labels);
    }
}
