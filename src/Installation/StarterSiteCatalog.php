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
        return match($style) {
            'simple-illustrated' => [
                $page('home','Un accompagnement à votre rythme',[
                    $text('Présentez ici votre activité et votre manière d’accompagner les personnes. Ce texte est entièrement modifiable.'),
                    $cta('Découvrir les prestations','/prestations'),
                    $cards('Des solutions adaptées',[
                        ['Accompagnement','Expliquez les prestations que vous proposez.','/prestations'],
                        ['Votre approche','Présentez votre méthode et vos engagements.','/approche'],
                        ['Un rendez-vous','Indiquez comment prendre contact avant une prestation.','/rendez-vous'],
                    ]),
                    $info('En bref',[['Accompagnement','Individuel ou collectif selon vos services'],['Accueil','Précisez vos modalités et vos horaires']]),
                ],'Accueil'),
                $page('prestations','Vos prestations',[
                    $text('Décrivez vos services, leurs objectifs et les personnes auxquelles ils s’adressent.'),
                    $cards('Prestations',[['Premier échange','Présenter les besoins et envisager une suite.'],['Accompagnement','Expliquer la durée et les modalités.'],['Atelier','Présenter un format collectif, si vous en proposez un.']]),
                    $cta('Demander des informations','/contact'),
                ],'Prestations'),
                $page('approche','Votre approche',[
                    $text('Présentez votre parcours, vos convictions et le cadre de votre activité.'),
                    $steps('Comment se déroule un accompagnement ?',[
                        ['Faire connaissance','Un premier échange pour comprendre les attentes.'],
                        ['Construire le parcours','Une proposition adaptée aux besoins exprimés.'],
                        ['Faire le point','Un bilan pour mesurer les progrès et envisager la suite.'],
                    ]),
                    $cta('Nous contacter','/contact'),
                ],'Approche'),
                $page('rendez-vous','Préparer un rendez-vous',[
                    $text('Présentez les conditions d’accueil, les disponibilités et la prise de contact. Le module de réservation reste facultatif et doit être activé séparément.'),
                    $info('Informations utiles',[['Lieu','À personnaliser'],['Disponibilités','À préciser'],['Réservation','Aucun rendez-vous n’est enregistré par ce contenu d’exemple']]),
                    $cta('Écrire un message','/contact'),
                ],'Rendez-vous'),
                $page('galerie','Images et réalisations',[
                    $text('Ajoutez vos photos depuis la médiathèque et renseignez leur description alternative.'),
                    $gallery('Galerie à compléter'),
                ],'Galerie'),
                $page('questions','Questions fréquentes',[
                    $faq('Avant de prendre contact',[
                        ['À qui s’adresse ce service ?','Précisez les publics et les besoins pris en charge.'],
                        ['Comment se déroule un premier échange ?','Indiquez votre méthode et les modalités pratiques.'],
                        ['Comment prendre rendez-vous ?','Renvoyez vers le module de réservation s’il est activé, ou vers le contact.'],
                    ]),
                    $cta('Poser une autre question','/contact'),
                ],'FAQ'),
                $page('contact','Nous contacter',[
                    $text('Indiquez vos coordonnées et moyens de contact. Aucun formulaire ni envoi automatique n’est activé dans cet exemple.'),
                    $info('Coordonnées à renseigner',[['E-mail','Adresse à personnaliser'],['Secteur','Ville et zone de déplacement']]),
                ],'Contact'),
            ],
            'quiet-elegant' => [
                $page('home','Un séjour à votre rythme',[
                    $text('Une maison, un lieu de repos, une atmosphère à raconter. Remplacez ce texte et les photos pour présenter votre établissement.'),
                    $cta('Découvrir les chambres','/chambres'),
                    $cards('Votre séjour',[['Les chambres','Les espaces privés et leur caractère.','/chambres'],['Le lieu','La maison, le jardin et les espaces communs.','/maison'],['Aux alentours','Idées de promenade et informations locales.','/decouvrir']]),
                ],'Accueil'),
                $page('maison','La maison',[
                    $text('Racontez l’histoire du lieu et ce qui le rend particulier.'),
                    $info('La maison en pratique',[['Capacité','À préciser'],['Accueil','Modalités à préciser'],['Accessibilité','Informations à compléter']]),
                    $gallery('La maison en images'),
                ],'La maison'),
                $page('chambres','Chambres et hébergements',[
                    $text('Présentez les chambres avec leurs prestations et leurs équipements.'),
                    $cards('Les espaces',[['Chambre 1','Description et équipements à personnaliser.'],['Chambre 2','Description et équipements à personnaliser.'],['Espaces communs','Ce qui est accessible aux visiteurs.']]),
                    $gallery('Photos des chambres'),
                ],'Chambres'),
                $page('sejour','Préparer votre séjour',[
                    $steps('Les étapes',[['Découvrir','Choisissez un hébergement adapté.'],['Contacter','Confirmez les conditions et les disponibilités.'],['Arriver','Retrouvez les informations pratiques avant le séjour.']]),
                    $faq('Questions fréquentes',[['Comment réserver ?','La réservation en ligne nécessite un module distinct.'],['Quelles sont les heures d’arrivée ?','Précisez les horaires propres à votre hébergement.']]),
                ],'Séjour'),
                $page('decouvrir','Découvrir les environs',[
                    $text('Présentez les paysages, les activités et les lieux autour de votre établissement.'),
                    $cards('À découvrir',[['Balades','Itinéraires et paysages à proximité.'],['Culture','Patrimoine et rencontres locales.'],['Table et terroir','Commerces et producteurs du territoire.']]),
                    $gallery('À voir autour du lieu'),
                ],'À découvrir'),
                $page('contact','Contact et accès',[
                    $info('Informations pratiques',[['Adresse','À personnaliser'],['Accès','Précisez les transports et le stationnement'],['Contact','À renseigner']]),
                    $text('Ce modèle ne recueille pas de coordonnées ni de réservation sans l’activation d’un module dédié.'),
                ],'Contact'),
            ],
            'image-forward' => [
                $page('home','Photographier et regarder autrement',[
                    $text('Présentez votre univers photographique et vos dernières séries. Remplacez les images d’exemple par les vôtres depuis la médiathèque.'),
                    $cta('Explorer les galeries','/galeries'),
                    $cards('À parcourir',[['Les séries','Parcourir les ensembles photographiques.','/series'],['Les ateliers','Découvrir les rencontres et les pratiques.','/ateliers'],['La démarche','Comprendre les techniques et les choix du projet.','/demarche']]),
                    $gallery('Photographies à la une'),
                ],'Accueil'),
                $page('galeries','Galeries photographiques',[
                    $text('Rassemblez les photographies par album. Ajoutez leurs légendes et leurs descriptions accessibles dans la médiathèque.'),
                    $gallery('Albums et sélections'),
                    $cta('Contacter l’auteur','/contact'),
                ],'Galeries'),
                $page('series','Séries et projets',[
                    $cards('Projets photographiques',[['Série 01','Une série et son contexte à documenter.'],['Série 02','Un autre territoire, une autre recherche.'],['Archives','Photographies plus anciennes à valoriser.']]),
                    $gallery('Photographies des séries'),
                ],'Séries'),
                $page('ateliers','Ateliers et rencontres',[
                    $text('Présentez les ateliers proposés et conservez un historique des rencontres passées.'),
                    $steps('Participer à un atelier',[['Choisir un atelier','Consulter la présentation et les conditions.'],['Demander sa place','Contacter l’organisation ; aucune réservation n’est automatiquement créée.'],['Participer','Retrouver les informations pratiques et le matériel conseillé.']]),
                    $cards('Historique des ateliers',[['Atelier passé','Ajoutez ici un atelier et ses photographies.']]),
                ],'Ateliers'),
                $page('stenope','Découvrir le sténopé',[
                    $text('Une photographie sans objectif complexe : présentez ici le dispositif, son principe, les prises de vue et la démarche.'),
                    $cards('La pratique',[['Le matériel','Expliquer la chambre noire et les supports.'],['La prise de vue','L’exposition, le cadrage et le temps.'],['Le développement','La transformation de l’image.']]),
                    $gallery('Photographies au sténopé'),
                ],'Sténopé'),
                $page('demarche','À propos de la démarche',[
                    $text('Présentez l’auteur, le territoire, les méthodes et les liens entre les différentes séries.'),
                    $cta('Échanger','/contact'),
                ],'À propos'),
                $page('contact','Contact',[
                    $text('Ajoutez une adresse de contact ou activez un formulaire après avoir configuré les mesures anti-spam.'),
                ],'Contact'),
            ],
            'ultra-minimal' => [
                $page('home','Une organisation claire, du temps retrouvé',[
                    $text('Présentez votre activité de services, ses bénéfices concrets et son expérience.'),
                    $cta('Voir les prestations','/prestations'),
                    $cards('Vos besoins',[['Remettre en ordre','Documents et méthodes à simplifier.','/prestations'],['Gestion du quotidien','Suivi et échéances.','/prestations'],['Renfort ponctuel','Un appui lors d’une période chargée.','/prestations'],['Préparer un changement','Outils et nouvelles obligations.','/facturation']]),
                    $steps('Un accompagnement simple',[['Échanger','Identifier vos besoins.'],['Prioriser','Fixer les prochaines étapes.'],['Accompagner','Mettre en place un suivi adapté.']]),
                ],'Accueil'),
                $page('prestations','Prestations administratives',[
                    $text('Présentez les missions, leur périmètre et les modalités d’intervention.'),
                    $cards('Ce que vous pouvez déléguer',[['Documents','Classement, préparation et suivi.'],['Factures et devis','Suivi et relances validées.'],['Dossiers','Vérifications et pièces manquantes.'],['Transition','Préparation de nouveaux outils.']]),
                    $cta('Présenter votre besoin','/contact'),
                ],'Prestations'),
                $page('methode','Une méthode adaptée',[
                    $steps('Déroulement',[['Premier échange','Définir les objectifs.'],['Choix de la mission','Convenir du périmètre et du rythme.'],['Travail et suivi','Expliquer les interventions et les livrables.'],['Bilan','Faire le point et ajuster.']]),
                ],'Méthode'),
                $page('interlocuteur','Votre interlocuteur',[
                    $text('Présentez votre parcours, votre expérience, votre secteur et la façon dont les clients peuvent vous joindre.'),
                    $info('Le profil',[['Expérience','À compléter'],['Secteur','À compléter'],['Modalités','Sur place ou à distance, selon votre activité']]),
                ],'Votre interlocuteur'),
                $page('facturation','Préparer la facturation électronique',[
                    $text('Expliquez l’offre de préparation des données et le périmètre des contrôles. Les prix ci-dessous sont des exemples à adapter avant publication commerciale.'),
                    $prices('Tarifs à personnaliser',[['Offre découverte','Gratuit','Précisez les limites et les conditions.'],['Prestation complète','Sur devis','Décrivez les livrables, le calendrier et les règles de facturation.']]),
                    $steps('Le parcours',[['Préparer le fichier','Rassembler les données utiles.'],['Valider le périmètre','Confirmer les conditions de la mission.'],['Transmettre en privé','Le dépôt sécurisé doit être configuré séparément.'],['Vérifier les données','Contrôle des correspondances.'],['Restituer','Remettre les résultats selon les conditions convenues.']]),
                    $cta('Demander des informations','/contact'),
                ],'Facturation'),
                $page('outils','Outils utiles',[
                    $text('Décrivez les outils complémentaires et les recherches possibles. Les API, quotas, paiements et espaces clients ne sont pas activés par un simple thème.'),
                    $cards('Outils à raccorder',[['Recherche d’entreprise','Recherche SIREN et SIRET : module métier requis.'],['Dépôt de documents','Espace privé et droits d’accès requis.'],['Suivi de dossier','Historique des étapes et permissions à configurer.']]),
                ],'Outils'),
                $page('contact','Contact',[
                    $text('Ajoutez une adresse de contact et, si vous le souhaitez, activez un formulaire anti-spam après installation.'),
                    $info('Informations',[['E-mail','À compléter'],['Téléphone','À compléter'],['Localisation','À compléter']]),
                ],'Contact'),
            ],
            'graphic-rhythm' => [
                $page('home','Créer et se rencontrer',[
                    $text('Un événement ouvert, des rendez-vous variés et un programme à composer. Personnalisez les dates et les informations avant publication.'),
                    $cta('Voir le programme','/programme'),
                    $cards('Trois temps forts',[['Faire','Des ateliers concrets.','/ateliers'],['Rencontrer','Des échanges et des discussions.','/programme'],['Partager','Des temps collectifs.','/collectif']]),
                ],'Accueil'),
                $page('programme','Programme',[
                    $steps('Temps forts',[['Ouverture','Présentation de l’événement et de son intention.'],['Ateliers','Activités participatives à détailler.'],['Rencontres','Interventions et discussions.'],['Clôture','Restitution et échanges.']]),
                    $info('Repères',[['Dates','À renseigner'],['Lieu','À renseigner'],['Accès','À préciser']]),
                ],'Programme'),
                $page('ateliers','Ateliers',[
                    $cards('Participer',[['Atelier créatif','Matériel et objectifs à détailler.'],['Atelier numérique','Compétences et prérequis à présenter.'],['Atelier collectif','Une activité en équipe.']]),
                    $faq('Questions fréquentes',[['Faut-il s’inscrire ?','Précisez les conditions. Une inscription nécessite un module dédié.']]),
                ],'Ateliers'),
                $page('collectif','Le collectif',[
                    $text('Présentez l’équipe d’organisation, ses objectifs et ses engagements.'),
                    $cards('Le projet',[['Valeurs','Une démarche ouverte et accessible.'],['Équipe','Les personnes qui portent le projet.'],['Contributions','Les façons de participer.']]),
                ],'Le collectif'),
                $page('partenaires','Partenaires',[
                    $text('Présentez les structures partenaires et le rôle de chacune.'),
                    $cards('Nos soutiens',[['Partenaire 1','Description à personnaliser.'],['Partenaire 2','Description à personnaliser.']]),
                ],'Partenaires'),
                $page('infos-pratiques','Informations pratiques',[
                    $info('Préparer sa venue',[['Adresse','À renseigner'],['Horaires','À renseigner'],['Accessibilité','À renseigner'],['Contact','À renseigner']]),
                    $faq('Questions fréquentes',[['L’événement est-il accessible ?','Ajoutez les modalités d’accessibilité.'],['Comment participer ?','Expliquez les conditions de participation.']]),
                ],'Infos pratiques'),
                $page('contact','Contact',[$text('Indiquez un contact pour les visiteurs et les partenaires.')],'Contact'),
            ],
            'editorial' => [
                $page('home','À la une : prendre le temps de comprendre',[
                    $text('Présentez votre ligne éditoriale et les sujets que vous traitez. Cet espace ne publie aucun article automatiquement.'),
                    $cta('Lire nos analyses','/analyses'),
                    $cards('À la une',[['Enquête principale','Mettez ici un article majeur.','/analyses'],['Comprendre','Éclairer un sujet de fond.','/dossiers'],['Décrypter','Comparer les discours et les faits.','/rubriques']]),
                    $info('Nos engagements',[['Sources','Vérifier et attribuer les informations'],['Clarté','Expliquer les méthodes et les limites'],['Indépendance','Préciser le fonctionnement du média']]),
                ],'Accueil'),
                $page('analyses','Analyses',[
                    $text('Organisez vos analyses longues : contexte, faits, arguments, sources et conclusions.'),
                    $cards('Sujets à développer',[['Analyse 1','Un premier dossier à rédiger.'],['Analyse 2','Une autre perspective à documenter.']]),
                ],'Analyses'),
                $page('dossiers','Dossiers et comparatifs',[
                    $text('Regroupez vos documents, entretiens et comparatifs par thème. Chaque sujet peut disposer de sa propre page.'),
                    $steps('Notre méthode',[['Identifier le sujet','Définir la question étudiée.'],['Recouper','Examiner plusieurs sources.'],['Comparer','Présenter les éléments de façon transparente.'],['Publier','Préciser les corrections et les limites.']]),
                ],'Dossiers'),
                $page('rubriques','Rubriques',[
                    $cards('Explorer le média',[['Société','Articles et entretiens.'],['Écologie','Informations et analyses.'],['Numérique','Droits et usages.'],['Politique','Discours et décisions publiques.']]),
                    $cta('Consulter les analyses','/analyses'),
                ],'Rubriques'),
                $page('entretiens','Entretiens',[
                    $text('Présentez des entretiens écrits ou enregistrés et expliquez leur contexte. N’affichez un contenu externe qu’après consentement.'),
                    $cards('Rencontres',[['Entretien à venir','Ajoutez l’entretien et les intervenants autorisés.']]),
                ],'Entretiens'),
                $page('a-propos','À propos et charte éditoriale',[
                    $text('Présentez les personnes impliquées, les objectifs éditoriaux, le financement, la méthode et les corrections possibles.'),
                    $faq('Notre fonctionnement',[['Comment sont vérifiées les informations ?','Précisez les vérifications et les sources.'],['Comment demander une correction ?','Indiquez un canal de contact et une méthode de traitement.']]),
                ],'À propos'),
                $page('contact','Contact et contributions',[
                    $text('Indiquez une adresse pour les lecteurs, les sources, les propositions d’articles et les demandes de rectification.'),
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

