# Installer et utiliser Dendrila CMS

Cette version réunit le noyau, l’administration native, les médias et le forum dans une installation autonome. Elle ne modifie pas les sites existants et ne remplace pas leurs adaptateurs d’identité.

### Assistance pour les installations sur hébergement tiers

La vérification de propriété de l’étape « Accès » est obligatoire lorsque Dendrila est installé chez l’hébergeur du propriétaire (o2switch, OVHcloud, Infomaniak, Hostinger, IONOS, etc.). Si le propriétaire ne dispose pas lui-même du gestionnaire de fichiers, il peut demander à la personne qui administre son compte d’hébergement de déposer le fichier temporaire dans le dossier privé `config/`, sans lui demander de révéler son mot de passe. L’installateur n’a pas pour vocation de vérifier une future installation dans une offre hébergée directement par Dendrila sous une adresse telle que `nom.dendrila.org` : ce parcours reste distinct et ne doit pas être présenté ici comme déjà opérationnel.

## Hébergement

- PHP 8.5 ou ultérieur et Composer 2 ; extensions PDO SQLite, mbstring, ctype, sodium, fileinfo et GD. PDO MySQL reste utile uniquement aux anciennes installations MariaDB.
- SQLite est le stockage local par défaut, automatiquement créé dans `storage/site.sqlite`. Aucune base externe ni aucun compte SQL ne sont requis pour une nouvelle vitrine.
- HTTPS et une application d’authentification TOTP (6 chiffres, 30 secondes).
- Racine web strictement dirigée vers `public/`. `config/`, `storage/`, `vendor/` et le code serveur doivent rester hors de la racine publique.
- Le processus PHP doit pouvoir écrire dans `config/` et `storage/`, ainsi que dans son répertoire de sessions. Ces dossiers ne doivent pas être accessibles aux autres utilisateurs du serveur.

## Installation sans SSH ni Composer sur l'hébergement

Un paquet prêt à extraire est généré par le workflow GitHub Actions **Build installable Dendrila ZIP**. L'archive `dendrila-cms-web-install.zip` contient les classes PHP, les six thèmes avec leurs médias et l'autoloader Composer de **production**. Aucune connexion Composer ni accès SSH n'est requis pour l'installation sur l'hébergement.

1. Téléchargez l'artifact `dendrila-cms-web-install` du workflow GitHub, puis extrayez l'archive dans un **dossier privé** accessible depuis le gestionnaire de fichiers (par exemple `/home/compte/apps/dendrila-cms/`), sans remplacer de site existant.
2. Dans la gestion de domaines de l'hébergeur, configurez le **DocumentRoot du domaine** vers le sous-dossier `dendrila-cms/public/` uniquement. **Ne placez jamais** `config/`, `src/`, `vendor/` ou `storage/` dans la racine publique. Si l'hébergeur ne permet pas cette configuration, arrêtez ici : il faut une autre méthode de déploiement, pas un contournement de protection.
3. Activez PHP 8.5+, HTTPS et les extensions requises. Ouvrez `https://votre-domaine/preflight.php` pour vérifier les prérequis et les permissions.
4. Aucun raccordement à une base externe : vérifiez simplement que le dossier privé `storage/` est inscriptible. SQLite sera initialisé automatiquement après validation du compte administrateur.
5. Ouvrez `https://votre-domaine/` : l'accueil redirige vers `/install.php`. Dans « Accès », choisissez votre hébergeur (o2switch, Infomaniak, Hostinger, IONOS, OVHcloud ou « Autre / Je ne sais pas ») : l'aide affiche automatiquement les instructions du fournisseur et un lien vers sa documentation.
6. Cliquez sur « Télécharger le fichier prêt à déposer ». Dans le gestionnaire de fichiers de l'hébergeur, placez `ownership-proof.txt` dans **`dendrila-cms/config/` hors de la racine publique**, à côté de `public/`, jamais dedans. Cliquez sur « Vérifier et continuer ». Dendrila lit puis supprime ce fichier. La preuve n'est jamais stockée en clair côté navigateur au-delà de la session nécessaire ; le téléchargement n'est pas mis en cache. Le contrôle refuse toute preuve absente, erronée ou réutilisée. Pour les personnes expérimentées, l'ancienne clé CLI reste disponible derrière « Option avancée ».
7. Choisissez le thème, les modules utiles et votre compte avec TOTP : **l'étape de raccordement MariaDB est supprimée** pour les sites SQLite. Le fichier SQLite et son schéma sont créés automatiquement dans le stockage privé. La fin de l'installation verrouille le parcours, publie une page d'accueil et crée le menu.

**Pourquoi choisir l'hébergeur ?** Le sélecteur n'envoie aucune information à un tiers et ne change pas les contrôles de sécurité. Il adapte simplement les noms des menus du panneau d'hébergement et les liens d'aide. En cas d'hébergement non listé ou de JavaScript désactivé, « Autre / Je ne sais pas » reste utilisable ; le bouton sans JavaScript permet aussi d'afficher le guide choisi. L'outil ne peut pas configurer lui-même les permissions, le DocumentRoot, ni créer automatiquement une base dans le compte de l'hébergeur. En particulier, OVHcloud n'offre plus l'ancien explorateur FTP depuis le navigateur : son guide ne le présente pas comme disponible.

**Important :** l'archive n'est pas un installateur qui peut être jeté dans `public_html/` sans configuration. Le découpage entre racine publique et fichiers privés est une condition de sécurité. La preuve de propriété exige un accès au gestionnaire de fichiers, mais **aucun terminal ni clé de préparation CLI**. Le code ne remplace pas automatiquement un site client existant et ne modifie pas le DNS.

La CI produit le ZIP avec `python3 bin/build-web-release.py` après `composer install --no-dev --optimize-autoloader`, puis l'extrait dans un dossier neuf et vérifie en HTTP local : diagnostic, première visite redirigée, preuve hors webroot, affichage du sélecteur des thèmes et absence d'exposition d'un fichier privé. Les tests de publication et de restauration MariaDB sont exécutés séparément.

## Installation neuve avec terminal (alternative)

Depuis la racine du dépôt :

```sh
composer install --no-dev --optimize-autoloader
php bin/prepare-install.php
```

Conserver la clé affichée, valable une heure, puis ouvrir `https://votre-domaine/install.php`.

1. Saisir la clé d’installation.
2. Choisir parmi les six thèmes, avec leurs aperçus du même contenu.
3. Cocher les modules utiles : médiathèque et/ou forum. Le module externe Booking est indiqué comme non encore raccordé : il n'est pas installable via ce formulaire.
4. Le stockage SQLite privé est créé automatiquement ; aucun identifiant SQL n'est demandé.
5. Donner un nom et une origine HTTPS au site, créer le compte propriétaire et enregistrer la clé TOTP dans une application d’authentification. Conserver aussi cette clé dans un gestionnaire de mots de passe.
6. Valider le code courant. Attendre le code suivant pour ouvrir `/admin/`.

SQLite reste une base de données relationnelle : elle conserve les pages, révisions, comptes, permissions et navigation **sans serveur ni identifiants SQL externes**. Les modules cochés partagent le même fichier local privé. Il ne faut pas le placer dans le dossier public.

Une page d’accueil publiée et un menu sont créés. La configuration privée est écrite dans `config/local.php` et l’installation est verrouillée. Les variables du serveur prévalent sur ce fichier : ne pas laisser d’anciennes variables DB_DSN/DB_USERNAME/DB_PASSWORD sur un nouvel hébergement.

En cas d’échec après création de tables, consulter les journaux et reprendre avec une nouvelle base vide. L’installateur n’efface jamais une base existante. Pour renouveler une clé expirée avant installation, relancer la commande de préparation.

## Routage web

Le serveur doit servir les fichiers existants et envoyer les autres URL vers `public/index.php`. Les répertoires `/admin/` et `/forum/` utilisent leur `index.php`. Exemple Nginx à adapter au socket PHP de l’hébergement :

```nginx
root /srv/dendrila/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param HTTPS on;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
}
```

Le bloc doit être placé dans un serveur TLS. Si TLS est terminé par un proxy, configurer le serveur PHP pour reconnaître HTTPS à partir de ce proxy de confiance ; l’application ne fait pas confiance aveuglément à un en-tête transmis par le visiteur.

Pour un essai strictement local : `APP_ENV=local php -S 127.0.0.1:8080 -t public public/router.php`. Le mode local accepte HTTP uniquement depuis la boucle locale ; ne pas l’exposer publiquement.

## Éditer et publier

Dans `/admin/`, créer une page ou ouvrir une page existante. Modifier les textes, ajouter et ordonner les blocs, enregistrer chaque bloc, puis prévisualiser et publier. Un enregistrement ne publie pas : le site continue d’afficher la dernière version publiée. Compléter les champs obligatoires des blocs avant publication.

Les blocs comprennent texte, texte enrichi, image, galerie, bouton, intégration, cartes, questions/réponses, valeurs et statistiques. Les blocs inconnus déjà présents sont conservés. Pour les images, téléverser les fichiers dans la médiathèque puis sélectionner leur référence ; les fichiers privés sont servis via le point d’accès média.

Le menu permet d’ajouter une page existante. Les changements de menu sont visibles après leur enregistrement. Les permissions du compte sont vérifiées par le serveur à chaque action.

## Personnaliser l'apparence

Après connexion au compte propriétaire ou administrateur, ouvrir `/admin/appearance.php` depuis le tableau de bord. Choisir l’un des six thèmes livrés, activer seulement les couleurs de marque à remplacer, choisir éventuellement deux familles de police système et indiquer les URLs HTTPS du logo et du logotype. Les couleurs désactivées sont héritées du thème.

L'enregistrement est protégé par un jeton CSRF et un verrou de version ; les URL, couleurs et polices sont validées par les mêmes contrats que le moteur de thèmes. Un couple explicite texte/fond doit respecter un contraste minimum de 4,5:1. Les visiteurs et les aperçus utilisent les nouveaux réglages immédiatement, sans modifier les contenus publiés ni installer de fichiers distants. Les applications MMW voient la version de configuration augmenter. Les variables `DENDRILA_BRAND_JSON` de déploiement conservent leur priorité pour leurs clés.

Cette fonctionnalité s'applique au front natif Dendrila. Un site client conservant son HTML et sa CSS historiques (ex. Prestadmin) doit garder son adaptateur visuel dédié ; ne jamais lui appliquer automatiquement l'habillage générique.

## Comptes et forum

Le propriétaire peut générer un lien d’invitation depuis `/admin/accounts.php`. Choisir un rôle adapté ; le rôle membre permet de participer au forum sans éditer les pages. Partager le lien par son canal habituel : aucun e-mail n’est envoyé automatiquement. Le lien expire après 48 heures et ne sert qu’une fois. L’invité définit son mot de passe et son TOTP.

La désactivation d’un compte révoque ses sessions. Le propriétaire ne peut pas se désactiver lui-même par cette interface. Il n’existe pas encore de parcours public « mot de passe oublié » ni de codes de secours : conserver les identifiants et la clé TOTP en lieu sûr.

Si le forum est activé, `/forum/` affiche les discussions publiées. Les membres connectés soumettent leurs messages ; un modérateur les publie ou les rejette et peut verrouiller une discussion. L’interface de cette version gère le texte ; le service technique de photos du forum n’est pas encore exposé dans ce formulaire.

## Sauvegarder et mettre à jour

Sauvegarder ensemble le dossier `storage/` (qui contient `site.sqlite`), et `config/local.php` dans un emplacement privé. Une sauvegarde cohérente du fichier SQLite se réalise sans écrire en parallèle dans la base ou par l'API de sauvegarde SQLite. La clé d’authentification du fichier de configuration est nécessaire pour lire les secrets TOTP chiffrés. Tester une restauration sur un hébergement séparé avant la première mise en production.

Pour une mise à jour : sauvegarder, suspendre les écritures, déployer la version de code retenue, exécuter Composer, lancer `php bin/migrate.php`. Le script charge la configuration privée du site ; les variables du serveur restent prioritaires. Vérifier le site public, la connexion et la publication avant réouverture. Ne jamais relancer l’installation sur une base existante.

Les contrats MMW v1 restent disponibles. L’association d’un client MMW et le remplacement des adaptateurs de sites historiques nécessitent une validation dédiée ; les sessions web natives ne créent pas automatiquement un appareil MMW.

## Vérification

`composer lint` et `composer test` exécutent les contrôles sans base. Les tests PDO exigent des bases de test dédiées ; ne jamais leur fournir une base de production. `tests/install-pdo-run.php` utilise `INSTALL_DB_NAME` et les accès DB. `tests/install-browser.cjs` attend une base vide `cms_browser_test`, PHP, Playwright et le serveur local sur le port 8080. Le workflow Native privacy core prépare ces conditions sous PHP 8.5 / MariaDB 11.4.

## Stockage local SQLite

Le CMS accepte désormais SQLite pour les nouvelles installations, avec schéma versionné, transactions et connexion PDO dédiée. Le fichier est conservé dans `storage/site.sqlite`, jamais sous `public/`. Les tests isolés valident l’installation, l’authentification native mot de passe + TOTP, la publication et la relecture d’une page. Le code de l’ancienne installation MariaDB reste présent pour les sites concernés ; les anciens sites ne sont pas migrés automatiquement.

Le module externe Dendrila Booking existe dans son propre dépôt, mais son adaptateur d’installation et d’interface CMS n’est pas raccordé. Il figure en **non disponible** dans le sélecteur pour éviter l’activation trompeuse de réservation ou paiements. Aucune connexion MariaDB n’est donc demandée par l’installation actuelle. Le support complet du booking avec son propre schéma, ses webhooks et ses services fait l’objet d’un travail distinct.

Les galeries ne sont proposées que si la médiathèque est raccordée. Dans une collection (cartes, FAQ, informations, chiffres), enregistrer les champs avant d’ajouter, déplacer ou retirer un élément. Le maximum est de 50 éléments par collection. Les types texte et rich_text conservent les retours à la ligne mais échappent le HTML : il ne s’agit pas d’un éditeur HTML libre. Les contenus externes acceptent les adresses d’intégration HTTPS YouTube sans cookies, Vimeo et OpenStreetMap et restent bloqués avant consentement.

## Restaurer sur un autre hébergement

1. Sur une copie isolée, déployer la même version du code et installer ses dépendances.
2. Pour SQLite, restaurer `storage/site.sqlite` avec `config/local.php` et les autres données de `storage/` dans les emplacements privés. Ne jamais publier le fichier SQLite.
3. Pour une ancienne installation MariaDB uniquement, créer une base vide et y importer un dump SQL complet.
4. Adapter DB_DSN (chemin absolu du fichier SQLite), APP_BASE_URL, MEDIA_PRIVATE_ROOT, MEDIA_PUBLIC_ENDPOINT et FORUM_PRIVATE_ROOT aux nouveaux emplacements ; conserver DENDRILA_AUTH_KEY. Pour MariaDB, adapter aussi les identifiants SQL. Si des variables serveur existent, les adapter également.
5. Vérifier accueil, pages publiées, invisibilité des brouillons, images, TOTP et comptes désactivés avant toute bascule. Ne pas lancer l’assistant d’installation sur cette restauration.

La CI exécute un dump MariaDB et une archive des fichiers privés de test, les restaure dans une autre base et un autre répertoire, puis vérifie ces propriétés. Les archives privées de test ne sont pas publiées comme artifacts.

Les six thèmes exécutables et leurs ressources locales sont inclus dans le dépôt : aucun second checkout n’est nécessaire sur l’hébergement. Les aperçus d’installation et les pages publiques utilisent le même thème. Voir `docs/DESIGN-STYLES.md` pour les correspondances, l’identité de marque et la mise à jour versionnée des thèmes.

## Choix indépendant du parcours de contact

Le choix « Parcours guidé » se fait dans l'étape Modules, indépendamment du thème. Le formulaire classique reste le choix par défaut et ne crée aucune dépendance externe. L'assistant proposé est un outil natif à choix éditables : la présentation cartes / liste / compacte est modifiable ensuite depuis le back-office. Cette fonction n'est pas la reproduction intégrale du Jardinier conditionnel du Potager.
