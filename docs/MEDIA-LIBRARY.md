# Médiathèque native

La médiathèque gère des images JPEG, PNG et WebP. Chaque import reçoit un identifiant stable `m_` suivi de 32 caractères hexadécimaux, conservé lors des changements de texte et de rotation. Les SVG, fichiers exécutables et images non décodables sont refusés.

## Mise en service

1. Sauvegarder la base et appliquer `php bin/migrate.php` (migration `007_media.sql`).
2. Vérifier PHP 8.5, PDO MySQL, mbstring, fileinfo et GD avec WebP. Prévoir `memory_limit=256M`, `upload_max_filesize=10M` et `post_max_size=12M` au minimum pour les limites par défaut.
3. Créer un répertoire privé, **hors de toute racine web et de toute synchronisation Git→live**, accessible uniquement au compte PHP, par exemple `/home/COMPTE/private/dendrila-media` (0700). Aucun alias HTTP ne doit le servir.
4. Définir `MEDIA_PRIVATE_ROOT` et `MEDIA_PUBLIC_ENDPOINT=https://domaine.example/media.php` dans la configuration privée du serveur. L'URL est une valeur administrateur, jamais une valeur fournie par la requête. Installer `public/media.php` à cette URL.
5. Activer l'entrée MMW : `php bin/enable-media-library.php SITE_ID`. Cette commande conserve les autres modules et incrémente la configuration. Un second passage est sans effet.
6. Planifier `php bin/media-maintenance.php` une fois par jour, avec le même environnement privé et le même compte système que PHP.
7. Rafraîchir la configuration MMW. Vérifier un ajout, un changement de texte, une rotation et une suppression d'image inutilisée.

Limites configurables : `MEDIA_MAX_BYTES` (10 000 000 par défaut, plafond 25 000 000), `MEDIA_MAX_DIMENSION` (6000), `MEDIA_MAX_PIXELS` (12 000 000). L'envoi doit respecter à la fois ces limites et celles du module. La configuration publiée du module peut annoncer un plafond inférieur. Les originaux sont conservés sans modification ; les deux variantes WebP (`thumb` 320 px, `web` 1920 px au maximum) sont réencodées sans métadonnées de l'original.

Les images gérées ici sont destinées au site : les dérivés sont partageables par leur URL opaque, y compris avant leur insertion dans une page. Cette médiathèque n'est pas un coffre de documents confidentiels. Les originaux n'ont aucune route publique. Les métadonnées d'administration et les usages ne sont pas exposés par la route image. Seuls les dérivés de la version active sont servis, avec `nosniff` et sans cache ; les anciennes URLs cessent de fonctionner après rotation/suppression.

## API MMW v1

- `POST /v1/media` : multipart PHP natif (`$_FILES`), champs `module_id`, `item_id`, `client_request_id`, `metadata` (objet JSON) et fichier `media`.
- Pour la bibliothèque de site, `item_id` est le contexte de bibliothèque : **l'identifiant du module** (`media_library` dans la définition fournie). L'upload n'attache pas implicitement l'image à un contenu et ne publie aucune page.
- `GET /v1/snapshot` : `data[module_id].items`, avec `id`, `parent_id` égal au module, `title`, `subtitle`, `thumb`, `path` HTTPS et champs déclarés.
- `POST /v1/changes` : `update_item`, `rotate_left`, `rotate_right`, `delete_item`. Le payload contient `item_id` (asset), `parent_id` (module), puis seulement les métadonnées déclarées pour `update_item`.
- `GET /v1/media?module_id=media_library&query=arbre&mime=image/png&usage=unused&limit=100&offset=0` : recherche titre/nom/alt/crédit et filtres. `mime=image` accepte les formats image supportés. `usage` vaut `all`, `used` ou `unused`. Le snapshot est limité aux 100 premières images ; la route paginée permet de parcourir le reste.

Toutes ces routes exigent le jeton appareil et `X-Lepotager-Protocol: 1`. Les mutations ne sont jamais destinées à la file hors connexion. Le client MMW existant applique déjà `allowOffline=false` aux changements et aux uploads. Le serveur ne peut pas distinguer une requête retardée par un client non conforme d'une requête en ligne.

`media.read` est nécessaire pour la bibliothèque ; chaque écriture exige aussi `media.upload`, `media.update` ou `media.delete`. Les options de module viennent exclusivement de la configuration serveur. Les métadonnées déclarées acceptées sont `title`, `alt`, `caption`, `credit`, `kind`, `position` ; les types, longueurs, choix et bornes numériques sont revalidés. Les filtres d'usage exigent en plus `content.read_draft` : ils ne permettent pas à un simple lecteur de déduire l'existence de contenus privés. Aucun titre ou identifiant de contenu parent n'est renvoyé dans les erreurs de suppression.

Le champ `alt` est une **proposition par défaut**. Le contenu conserve son alt contextuel, y compris un alt vide volontaire. La médiathèque ne réécrit pas les blocs lors d'un changement de métadonnées. Pour le front, passer `$media->publicMap($siteId)` au paramètre existant `mediaUrls` du `PublicPageRenderer` ; ne pas fournir les chemins privés au renderer.

## Cohérence et reprise

- Une requête est identifiée par site, acteur et `client_request_id`. Un retry identique renvoie la première réponse ; un autre contenu avec le même identifiant est refusé.
- Le fichier original et les dérivés sont créés avant la validation transactionnelle en base. Une panne peut laisser des fichiers orphelins privés, mais aucune référence validée vers un fichier non créé. Le nettoyage efface les orphelins de plus d'une heure.
- Un verrou `flock` commun à cette racine de stockage sérialise les mutations de médias et la maintenance, y compris entre processus. Tous les workers doivent utiliser ce même stockage local ; ce premier lot ne vise pas plusieurs serveurs aux disques indépendants.
- La rotation crée une nouvelle version de dérivés à partir de l'original ; l'ancienne version reste en stockage pour traçabilité jusqu'à la suppression de l'asset. `version` désigne la version de représentation ; un changement de métadonnées incrémente la révision du site, pas la version des pixels.
- La suppression place d'abord l'asset en `deleting` dans une transaction. Il disparaît immédiatement des projections et de la route publique. La maintenance supprime les fichiers puis marque `deleted`. Un échec de fichier conserve `deleting` et le passage suivant reprend. La ligne tombstone et les réponses d'idempotence restent présentes.
- La vérification des usages relit les valeurs typées des contenus, sans `LIKE` sur le JSON : champs média, image, galerie et cartes. Elle vérifie le brouillon et la projection publiée stable. Les contenus archivés ne sont plus actifs ; leur restauration avec un média supprimé est refusée.
- Les écritures CMS verrouillent et valident les assets `m_…` avant enregistrement. La suppression verrouille l'asset puis relit les usages avec une lecture courante `FOR UPDATE`. Un conflit concurrent peut produire un deadlock MariaDB ; la transaction est annulée intégralement et peut être retentée avec le même identifiant, sans suppression partielle. Les anciens identifiants externes/des kits restent compatibles ; ils ne correspondent pas aux nouveaux assets gérés.

La recherche et le recalcul d'usage sont volontairement simples (lecture par site, sans index de références désynchronisable). Pour de très grandes bibliothèques, prévoir une projection indexée transactionnelle avant de généraliser cette stratégie.

## Validation et relais serveur

Les contrats images sont dans `tests/media-run.php`. `tests/media-pdo-run.php` couvre MariaDB, uploads/retries, droits, isolation de sites, rotations, métadonnées, usages brouillon/publication, suppression récupérable et routes HTTP. Ils sont intégrés aux workflows existants, sous PHP 8.5 et MariaDB 11.4.

Le prochain relais humain reste la validation o2switch : extensions GD/WebP, limites PHP, permissions/racine privée, tâche de maintenance, sauvegarde **cohérente base + stockage**, puis restauration sur une copie. Suspendre les écritures pendant cette sauvegarde et restaurer les deux ensembles ensemble. Ne pas activer Materceleste publiquement et ne pas retirer ses anciens outils dans ce lot.
