<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;

/** Theme-specific, read-only examples. Nothing here is installed into a new site. */
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
            'image-forward'=>'Horizons', 'ultra-minimal'=>'Studio Forme',
            'graphic-rhythm'=>'Les possibles', 'editorial'=>'Institut des territoires',
        }, 'tagline'=>'Démonstration · contenu fictif'];
    }
    public static function page(string $mode, string $style = 'simple-illustrated'): array
    {
        self::check($mode,$style);
        if ($mode === 'empty') return ['title'=>'Mon site','blocks'=>[['type'=>'text','text'=>'Bienvenue sur notre site.']]];
        $text=static fn(string $s):array=>['type'=>'text','text'=>$s];
        $cta=static fn(string $s):array=>['type'=>'cta','label'=>$s,'url'=>'#contenu'];
        $photo=static fn(string $id,string $alt):array=>['type'=>'image','media_id'=>$id,'alt'=>$alt];
        $cards=static fn(string $heading,array $items):array=>['type'=>'cards','heading'=>$heading,'items'=>$items];
        $gallery=static fn(array $ids):array=>['type'=>'gallery','heading'=>'Une sélection de réalisations','media_ids'=>$ids];
        $info=static fn(array $items):array=>['type'=>'key_values','heading'=>'En pratique','items'=>$items];
        return match($style) {
            'simple-illustrated'=>['title'=>'Un lieu, mille façons de participer.','blocks'=>[
                $text('Des ateliers, des espaces partagés et une équipe à votre écoute. Retrouvez simplement le service dont vous avez besoin.'),
                $photo('flowers','Fleurs du jardin partagé'),$cta('Découvrir les services'),
                $cards('Comment pouvons-nous vous aider ?',[
                    ['title'=>'Trouver un atelier','text'=>'Créer, apprendre et rencontrer les habitants.'],
                    ['title'=>'Utiliser un espace','text'=>'Une salle, un établi ou un coin de jardin.'],
                    ['title'=>'Rencontrer l’équipe','text'=>'Un accueil pour donner forme à vos idées.'],
                ]),$info([['label'=>'Accueil','value'=>'Du mardi au samedi'],['label'=>'Lieu','value'=>'La maison commune'],['label'=>'Participation','value'=>'Ouverte à toutes et tous']]),
            ]],
            'quiet-elegant'=>['title'=>'Prendre le temps. Retrouver l’essentiel.','blocks'=>[
                $text('Une maison au calme, des matières naturelles et une attention portée aux détails. Un espace pour se retrouver, tout simplement.'),
                $photo('forest','Lumière dans une forêt paisible'),$cta('Découvrir la maison'),
                $text('Nous imaginons des séjours à taille humaine. Quelques chambres, un jardin et des moments qui laissent la place à chacun.'),
                $gallery(['architecture','flowers']),$info([['label'=>'Le lieu','value'=>'Une maison dans la nature'],['label'=>'Accueil','value'=>'Sur rendez-vous']]),
            ]],
            'image-forward'=>['title'=>'Regarder autrement.','blocks'=>[
                $text('Paysages, espaces et lumières. Une sélection photographique, sans détour.'),
                $photo('mountains','Montagnes et village au bord du lac'),$cta('Parcourir la sélection'),
                $gallery(['architecture','forest','flowers','mountains']),
                $text('Chaque série explore un lieu et son atmosphère. Les photographies occupent le premier plan ; le texte les accompagne.'),
            ]],
            'ultra-minimal'=>['title'=>'Des idées claires. Des formes utiles.','blocks'=>[
                $text('Un studio indépendant pour concevoir des identités, des objets et des espaces durables.'),$cta('Parlons de votre projet'),
                $info([['label'=>'01 — Identité','value'=>'Direction artistique et systèmes graphiques'],['label'=>'02 — Édition','value'=>'Livres, documents et signalétique'],['label'=>'03 — Espace','value'=>'Lieux et parcours'],['label'=>'Disponibilité','value'=>'Nouveaux projets sur demande']]),
                $text('Une équipe courte, des échanges directs et des décisions expliquées. Du premier croquis à la réalisation.'),
            ]],
            'graphic-rhythm'=>['title'=>'FAIRE. ENSEMBLE.','blocks'=>[
                $text('Trois jours pour construire, essayer et partager. Un rendez-vous ouvert à toutes les curiosités.'),$cta('Découvrir le programme'),
                $cards('Le programme',[
                    ['title'=>'01 / Fabriquer','text'=>'Des ateliers pour passer de l’idée au premier objet.'],
                    ['title'=>'02 / Échanger','text'=>'Des rencontres courtes, ouvertes et concrètes.'],
                    ['title'=>'03 / Célébrer','text'=>'Une soirée pour réunir les énergies.'],
                ]),$info([['label'=>'Format','value'=>'Ateliers et rencontres'],['label'=>'Lieu','value'=>'La grande halle'],['label'=>'Public','value'=>'Toutes les curiosités']]),
            ]],
            'editorial'=>['title'=>'Comprendre les lieux. Accompagner leurs transformations.','blocks'=>[
                $text('Nous réunissons les savoirs et les expériences pour aider les territoires à construire leur avenir.'),
                $photo('architecture','Façade et composition architecturale'),$cta('Découvrir notre mission'),
                $text('Notre travail relie observation, transmission et action. Nous accompagnons les équipes locales avec des méthodes accessibles et des ressources partagées.'),
                $info([['label'=>'Observer','value'=>'Enquêtes de terrain et diagnostics'],['label'=>'Transmettre','value'=>'Formations et ressources'],['label'=>'Accompagner','value'=>'Démarches collectives et projets locaux']]),
                ['type'=>'statistics','heading'=>'Une action à taille humaine','items'=>[['value'=>'12','label'=>'territoires accompagnés'],['value'=>'24','label'=>'partenaires'],['value'=>'8','label'=>'années de terrain']]],
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
        $labels=$mode==='empty'?['Accueil']:match($style){
            'simple-illustrated'=>['Services','Le lieu','Contact'],'quiet-elegant'=>['La maison','L’esprit','Contact'],
            'image-forward'=>['Sélection','À propos','Contact'],'ultra-minimal'=>['Expertises','Approche','Contact'],
            'graphic-rhythm'=>['Programme','Le collectif','Infos pratiques'],'editorial'=>['Mission','Expertises','Contact'],
        };
        return array_map(static fn($label)=>['label'=>$label,'url'=>'#contenu'],$labels);
    }
}
