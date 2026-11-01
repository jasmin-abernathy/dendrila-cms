<?php
declare(strict_types=1);
namespace Dendrila\Cms\Installation;

use Dendrila\Cms\Content\ContentTypeDefinition;

/**
 * Editable site skeletons derived from reference-site section patterns.
 * No client data, paid endpoints, live booking, or external requests.
 * The visual theme remains separate; these are real CMS pages seeded at install.
 */
final class StarterSiteCatalog
{
    public const REFERENCES = [
        'simple-illustrated' => 'Materceleste — services, presentation, FAQ, images, appointments',
        'quiet-elegant' => 'Generic guesthouse demonstration (no named client reference)',
        'image-forward' => 'Oscura / Vues Imprenables — photos, series, workshops, pinhole',
        'ultra-minimal' => 'Prestadmin — services, method, profile, invoicing, business lookup',
        'graphic-rhythm' => 'Generic community-event demonstration (no named client reference)',
        'editorial' => 'advarP — lead story, sections, analyses, dossiers and editorial charter',
    ];

    private static function text(string $s): array { return ['type'=>'text','text'=>$s]; }
    private static function cta(string $label,string $url): array { return ['type'=>'cta','label'=>$label,'url'=>$url]; }
    private static function cards(string $heading,array $items): array {
        return ['type'=>'cards','heading'=>$heading,'items'=>array_map(
            static fn(array $i): array => ['title'=>$i[0],'text'=>$i[1],...($i[2]??[] ? ['url'=>$i[2]] : [])],$items
        )];
    }
    private static function steps(string $heading,array $items): array {
        return ['type'=>'steps','heading'=>$heading,'items'=>array_map(static fn(array $i): array => ['title'=>$i[0],'text'=>$i[1]],$items)];
    }
    private static function info(string $heading,array $items): array {
        return ['type'=>'key_values','heading'=>$heading,'items'=>array_map(static fn(array $i): array=>['label'=>$i[0],'value'=>$i[1]],$items)];
    }
    private static function faq(string $heading,array $items): array {
        return ['type'=>'faq','heading'=>$heading,'items'=>array_map(static fn(array $i):array=>['question'=>$i[0],'answer'=>$i[1]],$items)];
    }
    private static function prices(string $heading,array $items): array {
        return ['type'=>'pricing','heading'=>$heading,'items'=>array_map(static fn(array $i):array=>[
            'title'=>$i[0],'price'=>$i[1],'text'=>$i[2]
        ],$items)];
    }
    private static function gallery(string $heading): array { return ['type'=>'gallery','heading'=>$heading,'media_ids'=>[]]; }
    private static function page(string $slug,string $title,array $blocks,string $menuLabel=''): array {
        return ['slug'=>$slug,'title'=>$title,'menu_label'=>$menuLabel?:$title,'blocks'=>$blocks];
    }

    /** @return list<array{slug:string,title:string,menu_label:string,blocks:array}> */
    public static function pages(string $style): array
    {
        if (!array_key_exists($style,self::REFERENCES)) throw new \InvalidArgumentException('Unknown starter theme.');
        $text=[self::class,'text'];$cta=[self::class,'cta'];$cards=[self::class,'cards'];
        $steps=[self::class,'steps'];$faq=[self::class,'faq'];$info=[self::class,'info'];
        $gallery=[self::class,'gallery'];$prices=[self::class,'prices'];$page=[self::class,'page'];
        return match($style) {            'simple-illustrated' => [
                $page('home','Un espace pour vos projets',[
                    $text('Présentez ici votre activité, votre projet ou votre organisation. Ce texte est entièrement modifiable.'),
                    $cta('Découvrir nos services','/services'),
                    $cards('Ce que nous proposons',[
                        ['Services','Présentez vos activités et vos propositions.','/services'],
                        ['Présentation','Racontez votre démarche et vos engagements.','/a-propos'],
                        ['Informations','Indiquez les modalités de contact ou de participation.','/informations'],
                    ]),
                    $info('En bref',[['Activité','Présentez ce que vous proposez'],['Accueil','Précisez vos modalités et vos horaires']]),
                    $cta('Parlons de votre projet','/contact'),
                ],'Accueil'),
                $page('services','Nos services',[
                    $text('Décrivez les services, activités ou réalisations que vous souhaitez mettre en avant.'),
                    $cards('À découvrir',[['Proposition 1','Présentez un premier service ou projet.'],['Proposition 2','Précisez ce qui distingue une autre activité.'],['Proposition 3','Ajoutez une autre réalisation ou un service.']]),
                    $cta('Demander des informations','/contact'),
                ],'Services'),
                $page('a-propos','À propos',[
                    $text('Racontez l’histoire du projet, les personnes impliquées et vos valeurs.'),
                    $steps('Notre démarche',[
                        ['Découvrir','Présenter les besoins ou les objectifs.'],
                        ['Construire','Organiser les étapes du projet.'],
                        ['Faire le point','Partager les résultats et les prochaines étapes.'],
                    ]),
                    $cta('Nous contacter','/contact'),
                ],'À propos'),
                $page('informations','Informations pratiques',[
                    $text('Précisez les horaires, modalités d’accès ou de participation. Les éventuelles réservations nécessitent un module distinct.'),
                    $info('Informations utiles',[['Lieu','À personnaliser'],['Horaires','À préciser'],['Participation','À détailler ; aucune réservation n’est créée automatiquement']]),
                    $cta('Écrire un message','/contact'),
                ],'Informations'),
                $page('galerie','Galerie',[
                    $text('Ajoutez vos photos depuis la médiathèque et renseignez leur description alternative.'),
                    $gallery('Images à ajouter'),
                ],'Galerie'),
                $page('questions','Questions fréquentes',[
                    $faq('Questions fréquentes',[
                        ['Que proposez-vous ?','Présentez les activités, services ou ressources disponibles.'],
                        ['Comment participer ou vous contacter ?','Précisez les modalités pratiques.'],
                        ['Où trouver les informations ?','Renvoyez vers les pages pertinentes ou le contact.'],
                    ]),
                    $cta('Poser une autre question','/contact'),
                ],'FAQ'),
                $page('contact','Nous contacter',[
                    $text('Posez votre question ou présentez votre projet. Le formulaire est activé après configuration de l’adresse de réception.'),
                    $info('Coordonnées à renseigner',[['E-mail','Adresse à personnaliser'],['Secteur','Ville et zone de déplacement']]),
                    ['type'=>'contact_form','heading'=>'Envoyer un message'],
                ],'Contact'),
            ],
            'quiet-elegant' => [
                $page('home','Un lieu à découvrir',[
                    $text('Présentez ici un lieu, ses espaces, son atmosphère et ce qui le rend unique. Remplacez les textes et images pour construire votre propre présentation.'),
                    $cta('Découvrir les espaces','/espaces'),
                    $gallery('Images du lieu'),
                    $cards('Explorer le lieu',[['Les espaces','Présentez les pièces, espaces ou équipements disponibles.','/espaces'],['Présentation','Découvrez l’histoire et les caractéristiques du lieu.','/presentation'],['À découvrir','Activités, projets ou informations à découvrir.','/decouvrir']]),
                    $cta('Nous contacter','/contact'),
                ],'Accueil'),
                $page('presentation','Présentation',[
                    $text('Racontez l’histoire, la démarche ou le cadre de votre projet.'),
                    $info('En pratique',[['Accueil','Modalités à préciser'],['Services','À détailler'],['Accessibilité','Informations à compléter']]),
                    $gallery('Présentation en images'),
                ],'Présentation'),
                $page('espaces','Nos espaces',[
                    $text('Présentez les différents espaces, produits ou éléments de votre projet.'),
                    $cards('Les espaces',[['Espace 1','Description à personnaliser.'],['Espace 2','Autre élément à présenter.'],['Espaces communs','Informations sur les espaces partagés, si nécessaire.']]),
                    $gallery('Les espaces en images'),
                ],'Espaces'),
                $page('informations','Informations pratiques',[
                    $steps('Les étapes',[['Découvrir','Consultez les informations présentées.'],['Contacter','Vérifiez les possibilités et modalités.'],['Participer','Retrouvez les renseignements utiles.']]),
                    $faq('Questions fréquentes',[['Comment vous contacter ?','Indiquez les moyens de contact disponibles.'],['Quelles sont les modalités d’accueil ?','Précisez les horaires ou conditions propres à votre activité.']]),
                ],'Informations'),
                $page('decouvrir','À découvrir',[
                    $text('Mettez en avant vos coups de cœur, vos projets, vos ressources ou les activités locales.'),
                    $cards('À découvrir',[['Découvertes','Sélection de lieux ou de projets.'],['Activités','Suggestions et propositions.'],['Ressources','Informations et liens utiles.']]),
                    $gallery('Une sélection d’images'),
                ],'À découvrir'),
                $page('contact','Contact',[
                    $info('Informations pratiques',[['Adresse','À personnaliser'],['Accès','Précisez les modalités pratiques'],['Contact','À renseigner']]),
                    ['type'=>'contact_form','heading'=>'Envoyer un message'],
                    $text('L’envoi de messages nécessite une adresse de réception configurée. Aucun message n’est envoyé depuis les aperçus.'),
                ],'Contact'),
            ],
            'image-forward' => [
                $page('home','L’image au premier plan',[
                    $text('Présentez vos images, votre travail ou vos réalisations. Remplacez les médias d’exemple par les vôtres.'),
                    $cta('Découvrir les projets','/projets'),
                    $gallery('Sélection d’images'),
                    $cards('À parcourir',[['Projets','Découvrez les réalisations et leurs histoires.','/projets'],['Actualités','Suivez les événements ou nouveautés.','/actualites'],['À propos','Découvrez l’univers et les intentions du projet.','/a-propos']]),
                    $cta('Explorer la galerie','/galerie'),
                ],'Accueil'),
                $page('galerie','Galerie',[
                    $text('Présentez vos images et réalisations. Créez des albums, des légendes et des descriptions accessibles.'),
                    $gallery('Sélection d’images'),
                    $cta('Prendre contact','/contact'),
                ],'Galerie'),
                $page('projets','Projets',[
                    $cards('Projets à présenter',[['Projet 1','Présentez une réalisation et son contexte.'],['Projet 2','Décrivez un autre projet.'],['Archives','Sélection de travaux passés.']]),
                    $gallery('Images des projets'),
                ],'Projets'),
                $page('actualites','Actualités',[
                    $text('Présentez vos nouveautés, événements, ateliers ou publications récents.'),
                    $steps('Suivre les actualités',[['Découvrir','Consultez les nouveautés.'],['S’informer','Retrouvez les détails et les informations pratiques.'],['Participer','Contactez l’organisation si une activité est proposée.']]),
                    $cards('Archives',[['Actualité passée','Ajoutez une publication ou un événement précédent.']]),
                ],'Actualités'),

                $page('a-propos','À propos',[
                    $text('Présentez votre activité, votre parcours, votre démarche et les personnes impliquées.'),
                    $cta('Échanger','/contact'),
                ],'À propos'),
                $page('contact','Contact',[
                    $text('Pour une question, une collaboration ou un projet, utilisez le formulaire ci-dessous.'),
                    ['type'=>'contact_form','heading'=>'Envoyer un message'],
                ],'Contact'),
            ],
            'ultra-minimal' => [
                $page('home','L’essentiel, simplement',[
                    $text('Présentez votre activité, vos projets et vos principaux atouts.'),
                    $cta('Découvrir les services','/services'),
                    $cards('À découvrir',[['Service 1','Présentez votre offre principale.','/services'],['Service 2','Expliquez une autre proposition.','/services'],['Votre approche','Présentez vos méthodes.','/methode'],['Les offres','Découvrez les formules disponibles.','/offres']]),
                    $steps('Comment ça fonctionne',[['Découvrir','Présenter le projet ou le besoin.'],['Organiser','Définir les étapes utiles.'],['Réaliser','Mettre en œuvre et faire le point.']]),
                    $cta('Parler de votre besoin','/contact'),
                ],'Accueil'),
                $page('services','Services',[
                    $text('Présentez les services, produits ou compétences de votre activité.'),
                    $cards('Nos services',[['Service 1','Description du premier service.'],['Service 2','Description d’une autre offre.'],['Projet','Exemple de réalisation.'],['Accompagnement','Précisez vos modalités d’intervention.']]),
                    $cta('Présenter votre besoin','/contact'),
                ],'Services'),
                $page('methode','Une méthode adaptée',[
                    $steps('Déroulement',[['Premier échange','Définir les objectifs.'],['Choix de la mission','Convenir du périmètre et du rythme.'],['Travail et suivi','Expliquer les interventions et les livrables.'],['Bilan','Faire le point et ajuster.']]),
                ],'Méthode'),
                $page('a-propos','À propos',[
                    $text('Présentez l’équipe, l’histoire, les valeurs et les informations utiles sur votre activité.'),
                    $info('En bref',[['Expérience','À compléter'],['Secteur','À compléter'],['Modalités','À préciser']]),
                ],'À propos'),
                $page('offres','Offres et tarifs',[
                    $text('Présentez les différentes formules et leurs modalités. Les prix ci-dessous sont des exemples à adapter avant toute publication commerciale.'),
                    $prices('Offres à personnaliser',[['Découverte','Gratuit','Indiquez les conditions si vous proposez une offre gratuite.'],['Personnalisé','Sur devis','Présentez le périmètre et les modalités de votre offre.']]),
                    $steps('Le déroulement',[['Échanger','Décrire les besoins.'],['Définir','Préciser les modalités.'],['Démarrer','Organiser le travail ou la livraison.'],['Suivre','Faire le point sur l’avancement.'],['Conclure','Présenter les résultats.']]),
                    $cta('Demander des informations','/contact'),
                ],'Offres'),
                $page('ressources','Ressources',[
                    $text('Partagez des ressources, documents, outils ou liens utiles. Les modules interactifs restent à installer séparément.'),
                    $cards('À consulter',[['Documentation','Présentez des guides ou informations pratiques.'],['Outils','Ajoutez des fonctionnalités dédiées si nécessaire.'],['Liens utiles','Ressources complémentaires à sélectionner.']]),
                ],'Ressources'),
                $page('contact','Contact',[
                    $text('Présentez votre demande avec ce formulaire de contact.'),
                    $info('Informations',[['E-mail','À compléter'],['Téléphone','À compléter'],['Localisation','À compléter']]),
                    ['type'=>'contact_form','heading'=>'Envoyer un message'],
                ],'Contact'),
            ],
            'graphic-rhythm' => [
                $page('home','Des idées en mouvement',[
                    $text('Un projet vivant, des activités et des rencontres à présenter. Personnalisez les textes, dates et informations avant publication.'),
                    $cta('Voir les activités','/activites'),
                    $cards('À la une',[['Découvrir','Les activités et nouveautés.','/activites'],['Suivre','Les prochaines dates.','/agenda'],['Participer','Les différentes façons de s’impliquer.','/a-propos']]),
                    $steps('Le déroulement',[['Découvrir','Explorer le projet et ses activités.'],['Participer','Trouver la formule qui convient.'],['Partager','Suivre les résultats et prochains rendez-vous.']]),
                    $cta('Préparer votre visite','/infos-pratiques'),
                ],'Accueil'),
                $page('agenda','Agenda',[
                    $steps('Dates et temps forts',[['Première date','Présentez l’activité ou le rendez-vous.'],['Deuxième date','Ajoutez une nouvelle étape.'],['Autre rendez-vous','Décrivez un événement ou une actualité.'],['Bilan','Partagez les résultats ou les images.']]),
                    $info('Repères',[['Dates','À renseigner'],['Lieu','À renseigner'],['Informations','À préciser']]),
                ],'Agenda'),
                $page('activites','Activités',[
                    $cards('À découvrir',[['Activité 1','Description à personnaliser.'],['Activité 2','Compétences et informations à présenter.'],['Activité 3','Un projet ou événement complémentaire.']]),
                    $faq('Questions fréquentes',[['Comment participer ?','Précisez les modalités ; toute inscription nécessite un module dédié.']]),
                ],'Activités'),
                $page('a-propos','À propos',[
                    $text('Présentez le projet, les personnes qui y participent et les objectifs communs.'),
                    $cards('Le projet',[['Valeurs','Ce qui anime le projet.'],['Équipe','Les personnes impliquées.'],['Contributions','Les façons de participer.']]),
                ],'À propos'),
                $page('partenaires','Partenaires',[
                    $text('Présentez les structures partenaires et le rôle de chacune.'),
                    $cards('Nos soutiens',[['Partenaire 1','Description à personnaliser.'],['Partenaire 2','Description à personnaliser.']]),
                ],'Partenaires'),
                $page('infos-pratiques','Informations pratiques',[
                    $info('À savoir',[['Adresse','À renseigner'],['Horaires','À renseigner'],['Accessibilité','À renseigner'],['Contact','À renseigner']]),
                    $faq('Questions fréquentes',[['Le projet est-il accessible ?','Ajoutez les modalités d’accessibilité.'],['Comment prendre part aux activités ?','Expliquez les modalités de participation.']]),
                ],'Infos pratiques'),
                $page('contact','Contact',[$text('Pour échanger ou participer, envoyez un message avec le formulaire.'),['type'=>'contact_form','heading'=>'Écrivez-nous']],'Contact'),
            ],
            'editorial' => [
                $page('home','À la une',[
                    $text('Présentez vos publications, vos sujets et votre manière de les aborder. Aucun article n’est publié automatiquement.'),
                    $cta('Lire les publications','/publications'),
                    $cards('À découvrir',[['Publication à la une','Mettez ici un contenu important.','/publications'],['Dossiers','Approfondissez un sujet.','/dossiers'],['Thèmes','Parcourez les catégories.','/themes']]),
                    $info('En bref',[['Sujets','Présentez les catégories de publication'],['Méthode','Expliquez votre démarche'],['Contact','Indiquez comment vous joindre']]),
                    $cta('Proposer un sujet','/contact'),
                ],'Accueil'),
                $page('publications','Publications',[
                    $text('Présentez vos articles, actualités, études ou publications.'),
                    $cards('Publications à venir',[['Publication 1','Un contenu à rédiger.'],['Publication 2','Une autre publication à préparer.']]),
                ],'Publications'),
                $page('dossiers','Dossiers',[
                    $text('Regroupez vos contenus, ressources et documents autour de sujets communs.'),
                    $steps('Votre méthode',[['Choisir le sujet','Définir le thème.'],['Préparer','Rassembler les éléments utiles.'],['Organiser','Présenter les informations clairement.'],['Publier','Relire et mettre à disposition.']]),
                ],'Dossiers'),
                $page('themes','Thèmes',[
                    $cards('Explorer les sujets',[['Thème 1','Présentez une première catégorie.'],['Thème 2','Ajoutez un autre sujet.'],['Thème 3','Regroupez vos contenus.'],['Thème 4','Une autre sélection.']]),
                    $cta('Consulter les publications','/publications'),
                ],'Thèmes'),
                $page('rencontres','Rencontres',[
                    $text('Présentez vos échanges, entretiens, événements ou collaborations, avec leur contexte.'),
                    $cards('À découvrir',[['Rencontre à venir','Ajoutez une publication ou un événement.']]),
                ],'Rencontres'),
                $page('a-propos','À propos',[
                    $text('Présentez votre organisation, les personnes impliquées et les objectifs du projet.'),
                    $faq('Questions fréquentes',[['Comment sont préparés les contenus ?','Expliquez votre démarche.'],['Comment vous contacter ?','Indiquez les moyens de contact.']]),
                ],'À propos'),
                $page('contact','Contact',[
                    $text('Proposez un sujet ou prenez contact avec notre équipe.'),
                    ['type'=>'contact_form','heading'=>'Envoyer un message'],
                ],'Contact'),
            ],
        };
    }

    public static function navigation(string $style): array
    {
        $items=[];
        foreach(self::pages($style) as $page) {
            $items[]=['id'=>$page['slug'],'label'=>$page['menu_label'],
                'url'=>$page['slug']==='home'?'/':'/'.$page['slug'],'visible'=>true];
        }
        return $items;
    }

    public static function validatedPages(string $style): array
    {
        $schema=json_decode((string)file_get_contents(dirname(__DIR__,2).'/examples/content-types/page.json'),true,512,JSON_THROW_ON_ERROR);
        $type=new ContentTypeDefinition($schema);
        $slugs=[];
        $pages=self::pages($style);
        foreach ($pages as $page) {
            if (isset($slugs[$page['slug']])) throw new \RuntimeException('Duplicate starter URL');
            $slugs[$page['slug']]=true;
            $type->validateValues(['title'=>$page['title'],'slug'=>$page['slug'],'blocks'=>$page['blocks']]);
        }
        if (!isset($slugs['home'])) throw new \RuntimeException('Starter homepage missing');
        return $pages;
    }
}

