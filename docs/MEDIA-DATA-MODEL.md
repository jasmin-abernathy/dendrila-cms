# Contrat des données média — stabilisation du 8 octobre 2026

Ce contrat complète `MEDIA-LIBRARY.md`. Il fixe les données sur lesquelles construire l'interface. Il n'ajoute pas d'écran ni de nouveau protocole majeur.

## Entités et identité

| Entité | Clé | Rôle |
|---|---|---|
| `media_assets` | `(site_id, media_id)` | Identité de l'image, original privé, métadonnées et état |
| `media_variants` | `(site_id, media_id, version, kind)` | Dérivés WebP d'une version de pixels, `thumb` ou `web` |
| `idempotency_requests` | `(site_id, actor_id, client_request_id)` | Une réponse durable par opération logique |
| Usages | Recalcul à partir des contenus typés | Pas de compteur enregistré susceptible de se désynchroniser |

`media_id` = `m_` + 32 chiffres hexadécimaux aléatoires. Il ne change pas si on modifie le titre, l'alt ou la rotation. Deux imports explicites du même fichier peuvent produire deux assets ; seul le retry de la même requête doit retrouver le même asset. Aucun dédoublonnage par nom de fichier.

`parent_id` dans MMW désigne le **contexte de bibliothèque**, donc l'identifiant du module. Ce n'est ni le site ni la page qui utilise l'image. Plusieurs modules de médiathèque d'un même site peuvent présenter les mêmes assets ; ils n'établissent pas des ACL par dossier. L'isolation reste par site et permission média.

## Données de l'asset

| Champ stocké | Type / sens | Écriture |
|---|---|---|
| `original_key` | Clé aléatoire d'un fichier privé | Serveur uniquement, jamais dans une réponse API |
| `filename` | Nom d'origine nettoyé, 255 caractères maximum | Fixé à l'import ; ce n'est pas un chemin |
| `mime` | MIME réel de l'original : JPEG, PNG ou WebP | Déduit du fichier, jamais de son extension |
| `bytes` | Taille réelle de l'original | Serveur uniquement |
| `width`, `height` | Dimensions de l'original | Immuables après l'import |
| `metadata_json` | Objet décrit ci-dessous | Mutations validées selon les champs du module |
| `author_id` | Acteur ayant importé le fichier | Serveur uniquement ; absent de la projection générique |
| `rotation` | 0, 90, 180 ou 270 degrés horaires depuis l'original | Action de rotation autorisée |
| `version` | Version des pixels, entière à partir de 1 | Incrémentée uniquement par une rotation |
| `revision` | Révision de l'asset, entière à partir de 1 | Incrémentée par chaque mutation utilisateur appliquée |
| `state` | `ready`, `deleting`, `deleted` | Cycle serveur |
| `created_at`, `updated_at` | Dates UTC | Serveur uniquement |

`metadata_json` contient toujours les clés suivantes après un import natif :

| Clé | Valeur / limite globale | Valeur initiale |
|---|---|---|
| `title` | Texte, 255 caractères | Nom de fichier si non fourni |
| `alt` | Texte, 2000 caractères | Chaîne vide |
| `caption` | Texte, 2000 caractères | Chaîne vide |
| `credit` | Texte de crédit ou source, 2000 caractères | Chaîne vide |
| `kind` | Texte, 80 caractères | `normal` |
| `position` | Entier de -100000 à 100000 | 0 |

Les contraintes déclarées par le module peuvent être plus strictes (champ obligatoire, longueur, choix, bornes). Un champ non déclaré n'est pas éditable. `null`, tableaux et objets sont refusés pour les métadonnées texte. Une chaîne vide explicitement envoyée reste une chaîne vide : ne pas la remplacer silencieusement par une ancienne valeur.

Le champ `alt` est une suggestion pour une prochaine insertion. Un alt contextuel dans un bloc, y compris une chaîne vide pour une image décorative, reste prioritaire. Modifier l'asset ne modifie pas toutes les pages.

## Version des pixels et concurrence

La migration additive `008_media_revision.sql` ajoute `revision` sans modifier ni rejouer `007`. Les assets déjà présents commencent leur suivi de révision à 1 ; elle ne reconstitue pas un historique de métadonnées antérieur. La migration est rejouable et ne remet pas les révisions existantes à zéro.

- Import : `version=1`, `revision=1`.
- Modification des métadonnées : `revision + 1`, mêmes fichiers et même `version`.
- Rotation : `revision + 1`, `version + 1`, nouveaux dérivés, même original.
- Suppression logique : `revision + 1`, état `deleting`, dernière `version` des pixels conservée.
- Nettoyage physique : état `deleted` et horodatage actualisé ; aucun nouveau changement éditorial et aucune nouvelle révision utilisateur.
- Retry identique : même réponse, aucun nouvel incrément.

La future interface **doit** envoyer `payload.expected_revision`, entier positif égal à la révision affichée, pour chaque mise à jour, rotation et suppression. Le serveur compare cette révision sous verrou. Si le média a changé : HTTP 409 `conflict`, aucune écriture. Garder les saisies locales, proposer l'actualisation puis une nouvelle décision utilisateur ; ne pas renvoyer automatiquement en écrasant la révision attendue.

Pour rester compatible avec MMW v1 déjà livré, `expected_revision` reste facultatif côté serveur. Les clients qui l'omettent conservent le comportement historique : les opérations sont sérialisées, mais les métadonnées suivent la dernière écriture. Ne pas annoncer une prévention universelle des écrasements tant que tous les clients ne l'envoient pas.

L'idempotence est consultée **avant** la comparaison de révision : le retry d'une sauvegarde réussie continue à fonctionner après son incrément. Après un véritable conflit et relecture, une nouvelle opération choisie par l'utilisateur emploie un nouveau `client_request_id`.

## Projection privée pour l'interface

`GET /v1/media` et `data[module_id].items` exposent, sous `media.read` :

- `id`, `parent_id`, `title`, `subtitle`, `filename` ;
- `mime`, `bytes` pour l'original ;
- `original_width`, `original_height` : ne pas les confondre avec les dimensions après rotation ;
- `version`, `revision`, `rotation`, `state` ;
- `created_at`, `updated_at` en ISO 8601 UTC, suffixe `Z` ;
- `thumb` et `path`, conservés pour les clients MMW existants ;
- `variants.thumb` et `variants.web`, chacun avec `url`, `mime`, `width`, `height`, `bytes` ;
- les seules métadonnées déclarées dans `module.fields`.

Les champs techniques sont en lecture seule. Les afficher ne donne pas le droit de les renvoyer dans `update_item`. Il n'y a ni chemin de stockage, ni original téléchargeable, ni identifiant d'auteur, ni noms de pages privées dans cette projection. Pour l'interface, distinguer explicitement « Taille du fichier original » et « Taille de l'image optimisée ».

Seuls les médias `ready` sont listés. `publicMap(siteId)` reste une projection interne au serveur des URLs WebP, utilisable avec `PublicPageRenderer`. La route publique ne livre que le dérivé de la version active, jamais les métadonnées privées.

## Usages et états de suppression

Les références prises en charge sont : champ de type `media` (simple ou multiple), `image.media_id`, `gallery.media_ids`, `cards.items[].media_id`. La recherche d'usage consulte le brouillon **et** la version publiée stable de tout contenu non archivé. Les titres et autres chaînes libres ne sont pas des références.

Les contenus CMS qui écrivent un identifiant géré `m_…` doivent le faire dans une transaction et verrouiller l'asset du même site avant validation. Toute écriture hors transaction est refusée ; un média supprimé ou d'un autre site est indisponible. La suppression verrouille l'asset et relit les usages en lecture courante. En cas de deadlock, la transaction entière est annulée ; le client peut retenter la même opération idempotente, sans supprimer ses saisies.

Les anciennes révisions archivées ne constituent pas un usage actif. Une restauration qui réintroduirait un média supprimé doit être refusée. La restauration des fichiers supprimés n'est pas une fonction de cette première version.

Les filtres `usage=used|unused` nécessitent `content.read_draft` en plus de `media.read`. La suppression refuse un usage privé sans divulguer de titre ou d'identifiant de page. Aucun endpoint de liste détaillée des usages n'est fourni dans ce lot.

## Limites à préserver dans le relais

La bibliothèque contient des images destinées à être partageables par leur URL dérivée, y compris avant insertion. Ce n'est pas une médiathèque de documents confidentiels. Un futur besoin de pièces privées demandera une politique de visibilité et une livraison authentifiée distinctes.

La recherche parcourt les médias du site et les usages sont recalculés ; ne pas affirmer une indexation optimisée pour de gros volumes. Les contraintes ci-dessus sont garanties par les services ; les accès SQL directs administrateur/import hors services doivent conserver ces mêmes invariants.
