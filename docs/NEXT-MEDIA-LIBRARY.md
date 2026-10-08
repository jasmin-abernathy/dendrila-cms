# Relais d’implémentation — médiathèque native Dendrila — 7 octobre 2026

> Implémentation du 8 octobre : voir [MEDIA-LIBRARY.md](MEDIA-LIBRARY.md) pour le fonctionnement, les limites et la mise en service. Le contrat ci-dessous est conservé comme checklist de référence. La validation o2switch reste nécessaire avant activation.

## Point de départ vérifié

- Dépôt principal : `jasmin-abernathy/dendrila-cms`.
- Base de travail : `main` après le SEO natif, SHA `ce455ed5cd2b04d18fd0e2df0e2fb202d9b5d76a`.
- Navigation native déjà fusionnée : SHA `d0282564edbd8f032cbd43d2d4305a4a982568c2`.
- Contrat `media_library` désormais formalisé dans `jasmin-abernathy/lepotager-site-manager`, main SHA `98de156c85ef18d5866724520725519d8eadae83`.
- L’application MMW sait déjà afficher `media_library` et envoyer `update_item`, `rotate_left`, `rotate_right`, `delete_item` avec `allowOffline=false`.
- Dendrila dispose déjà des permissions `media.read`, `media.upload`, `media.update`, `media.delete` dans les rôles par défaut.
- `PublicPageRenderer` sait déjà recevoir une map `media_id => URL publique`; la médiathèque doit fournir cette projection, pas réécrire le renderer.

## But du prochain lot

Construire la bibliothèque média générique du CMS, sans logique Materceleste et sans nouveau protocole client. Elle doit couvrir stockage, métadonnées, variantes publiques, recherche, usages, suppression sûre, upload MMW et projection `media_library`.

## Contrat fonctionnel minimum

Un média possède un identifiant stable et opaque, un `site_id`, un MIME réel, une taille, des dimensions, un nom/titre d’administration, un alt par défaut facultatif, une légende, un crédit/source facultatif, un auteur, un état, des horodatages et des variantes dérivées.

Le texte alternatif d’un asset n’est **pas** l’unique alt de tous ses usages : un bloc de contenu peut continuer à fournir un alt contextuel qui prend priorité. L’asset peut seulement proposer une valeur par défaut.

Prévoir au minimum :

- recherche par titre/nom de fichier/alt/crédit ;
- filtres MIME/type et état d’usage ;
- variantes `thumb` et `web` optimisées ;
- identité stable lorsque le même média est réutilisé ;
- lecture publique uniquement via des URLs de dérivés autorisés ;
- original privé ou autrement non servi directement par un chemin fourni par le client ;
- rotation comme transformation gérée/versionnée sans destruction silencieuse de l’original ;
- suppression refusée tant qu’un usage actif existe.

## Persistance

La prochaine migration normale est `007_media.sql` (`005_navigation.sql`, puis `006_seo.sql` existent déjà). Concevoir des tables séparant au moins asset et variantes. Si un index d’usage est introduit, il doit être transactionnel avec les mutations CMS qui créent/retirent les références, ou disposer d’un recalcul fiable ; ne pas se contenter d’un `LIKE` sur du JSON pour autoriser une suppression.

Tous les accès SQL restent bornés par `site_id`. Ajouter des tests explicites de refus inter-sites.

## Upload MMW

Le contrat v1 est fixé : `POST /v1/media`, `multipart/form-data`, champs `module_id`, `item_id`, `client_request_id`, `metadata`, `media`.

`Http\Request` ne gère aujourd’hui que headers + body JSON. Ne pas bricoler un parseur multipart maison dans `jsonObject()`. Ajouter une abstraction d’upload ou un chemin HTTP séparé, testable, qui reçoit un fichier temporaire contrôlé et des champs scalaires.

Validation serveur obligatoire :

- erreur d’upload ;
- taille réelle ;
- MIME réel via `finfo` et non l’extension ;
- image réellement décodable ;
- dimensions bornées ;
- formats autorisés explicitement ;
- réencodage pour les dérivés afin de retirer métadonnées et contenu parasite ;
- aucun SVG/script exécutable accepté dans le premier lot sauf politique de désinfection dédiée et testée.

Les seuils initiaux doivent être configurables et raisonnables. Les médias ne sont jamais mis en file hors connexion.

## Idempotence et cohérence fichiers/BDD

`client_request_id` doit empêcher qu’un retry crée deux assets. Réutiliser la sémantique d’idempotence du CMS ou une couche équivalente clairement bornée au site/acteur.

Le système de fichiers n’est pas transactionnel avec MariaDB : concevoir les créations/suppressions en étapes récupérables. Ne jamais supprimer définitivement le fichier avant que l’état BDD permette de reprendre proprement après panne. Documenter nettoyage des temporaires et échecs partiels.

## `media_library` dans config/snapshot/changes

Mettre à jour `PdoSiteReadModel::safeModules()` pour accepter `media_library` avec validation stricte des options `allow_rotate` et `allow_delete` et des champs déjà supportés par MMW.

Le snapshot attendu sous `data[module_id].items` fournit :

- `id` ;
- `parent_id` pour le contexte de mutation ;
- `title`, `subtitle` facultatifs ;
- `thumb` HTTPS prioritaire ou `path` HTTPS de repli ;
- champs déclarés par le module (`alt`, `caption`, `kind`, `position`, etc.).

`PdoSiteChangeService` doit accepter pour `media_library` :

- `update_item` ;
- `rotate_left` ;
- `rotate_right` ;
- `delete_item` ;

et refuser toute action non annoncée par la configuration ou non permise. Ces mutations ne sont jamais offline. Elles doivent être idempotentes.

## Usages et suppression

Les références actuelles existent notamment dans :

- champ `media` des types de contenu ;
- bloc `image.media_id` ;
- `gallery.media_ids` ;
- `cards.items[].media_id`.

La suppression doit considérer au minimum les brouillons autorisés **et** la version publiée stable. Un asset encore référencé par l’une des deux ne doit pas disparaître. Prévoir un message exploitable par le back-office plutôt qu’une suppression partielle.

Le garde-fou documenté dans `repo-factory` s’applique : toute donnée annexe doit respecter la visibilité du contenu parent ; ne pas exposer des usages ou métadonnées privées à un simple `content.read`.

## Tests obligatoires

- création/upload valide et retry même `client_request_id` => un seul asset ;
- faux MIME refusé ;
- fichier trop gros refusé ;
- dimensions excessives refusées ;
- image corrompue refusée ;
- dérivés générés avec chemins serveur contrôlés ;
- rotation ne modifie pas l’original et rejouée ne double pas la transformation ;
- mise à jour métadonnées validée côté serveur ;
- suppression refusée si utilisée dans brouillon ou publication ;
- suppression autorisée si inutilisée et reprise sûre en cas d’échec fichiers ;
- isolation stricte entre deux sites ;
- snapshot `media_library` ne sort que les médias autorisés ;
- client ne peut fabriquer `allow_rotate`/`allow_delete` ;
- URLs publiques HTTPS/racine contrôlées, aucun chemin privé ;
- migrations idempotentes et tests MariaDB PHP 8.5.

## CI et méthode

Lire `AGENTS.md`/règles du dépôt avant mutations. Travailler sur une branche dédiée, commits propres, puis PR. Réutiliser les workflows existants plutôt qu’en créer un lourd. Si le lot touche la migration MariaDB et le front/API, faire passer les contrats PHP et le workflow MariaDB.

Ne pas modifier Mon Manager Web sauf découverte d’un vrai écart avec le contrat documenté au SHA `98de156…`. Ne pas ajouter de logique Materceleste.

## Après ce lot

Une fois la médiathèque fusionnée, le prochain point réellement humain sera la validation o2switch : PHP 8.5, extensions image retenues (`gd` ou `imagick`), chemins privés/permissions, MariaDB, sauvegarde/restauration. Pour Bookings, les clés sandbox Stripe/PayPal/Google et la configuration privée restent également des actions serveur/humaines avant activation.

Ne pas activer publiquement Materceleste ni retirer Easy!Appointments/AbanteCart pendant ce lot.

