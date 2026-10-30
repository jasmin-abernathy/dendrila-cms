# Installer et utiliser Dendrila CMS

Cette version réunit le noyau, l’administration native, les médias et le forum dans une installation autonome. Elle ne modifie pas les sites existants et ne remplace pas leurs adaptateurs d’identité.

## Hébergement

- PHP 8.5 ou ultérieur et Composer 2 ; extensions PDO MySQL, mbstring, ctype, sodium, fileinfo et GD.
- MariaDB 11.4 recommandée et une base **vide**, dédiée à ce site, en utf8mb4. Le compte SQL doit pouvoir créer les tables et modifier leurs données.
- HTTPS et une application d’authentification TOTP (6 chiffres, 30 secondes).
- Racine web strictement dirigée vers `public/`. `config/`, `storage/`, `vendor/` et le code serveur doivent rester hors de la racine publique.
- Le processus PHP doit pouvoir écrire dans `config/` et `storage/`, ainsi que dans son répertoire de sessions. Ces dossiers ne doivent pas être accessibles aux autres utilisateurs du serveur.

## Installation neuve

Depuis la racine du dépôt :

```sh
composer install --no-dev --optimize-autoloader
php bin/prepare-install.php
```

Conserver la clé affichée, valable une heure, puis ouvrir `https://votre-domaine/install.php`.

1. Saisir la clé d’installation.
2. Choisir parmi les six thèmes, avec leurs aperçus du même contenu.
3. Cocher les modules utiles : médiathèque et/ou forum.
4. Indiquer l’accès à la base dédiée. L’écran explique les besoins du noyau et des modules choisis.
5. Donner un nom et une origine HTTPS au site, créer le compte propriétaire et enregistrer la clé TOTP dans une application d’authentification. Conserver aussi cette clé dans un gestionnaire de mots de passe.
6. Valider le code courant. Attendre le code suivant pour ouvrir `/admin/`.

La base reste obligatoire même sans module : pages, révisions, comptes, permissions et navigation sont relationnels. Les modules partagent cette base ; le schéma complet est installé mais les fonctionnalités optionnelles restent désactivées si elles ne sont pas cochées. Cette version ne propose pas de mode sans base.

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

## Comptes et forum

Le propriétaire peut générer un lien d’invitation depuis `/admin/accounts.php`. Choisir un rôle adapté ; le rôle membre permet de participer au forum sans éditer les pages. Partager le lien par son canal habituel : aucun e-mail n’est envoyé automatiquement. Le lien expire après 48 heures et ne sert qu’une fois. L’invité définit son mot de passe et son TOTP.

La désactivation d’un compte révoque ses sessions. Le propriétaire ne peut pas se désactiver lui-même par cette interface. Il n’existe pas encore de parcours public « mot de passe oublié » ni de codes de secours : conserver les identifiants et la clé TOTP en lieu sûr.

Si le forum est activé, `/forum/` affiche les discussions publiées. Les membres connectés soumettent leurs messages ; un modérateur les publie ou les rejette et peut verrouiller une discussion. L’interface de cette version gère le texte ; le service technique de photos du forum n’est pas encore exposé dans ce formulaire.

## Sauvegarder et mettre à jour

Sauvegarder ensemble la base MariaDB, `storage/` et `config/local.php` dans un emplacement privé. La clé d’authentification du fichier de configuration est nécessaire pour lire les secrets TOTP chiffrés. Tester une restauration sur un hébergement séparé avant la première mise en production.

Pour une mise à jour : sauvegarder, suspendre les écritures, déployer la version de code retenue, exécuter Composer, lancer `php bin/migrate.php`. Le script charge la configuration privée du site ; les variables du serveur restent prioritaires. Vérifier le site public, la connexion et la publication avant réouverture. Ne jamais relancer l’installation sur une base existante.

Les contrats MMW v1 restent disponibles. L’association d’un client MMW et le remplacement des adaptateurs de sites historiques nécessitent une validation dédiée ; les sessions web natives ne créent pas automatiquement un appareil MMW.

## Vérification

`composer lint` et `composer test` exécutent les contrôles sans base. Les tests PDO exigent des bases de test dédiées ; ne jamais leur fournir une base de production. `tests/install-pdo-run.php` utilise `INSTALL_DB_NAME` et les accès DB. `tests/install-browser.cjs` attend une base vide `cms_browser_test`, PHP, Playwright et le serveur local sur le port 8080. Le workflow Native privacy core prépare ces conditions sous PHP 8.5 / MariaDB 11.4.

## Périmètre de stockage de la V1

Le choix retenu est un noyau MariaDB unique, même pour une vitrine : les transactions, les révisions publiées, les permissions et les révocations reposent sur ce stockage. Retirer seulement l’écran de connexion ne créerait pas un mode sans base fiable. Un stockage de fichiers ou SQLite demanderait un adaptateur, des migrations et les mêmes tests de concurrence et de restauration ; ce mode reste une évolution distincte, non disponible dans cette V1. L’installateur affiche donc toujours la base, avec les raisons correspondant aux modules cochés.

Les galeries ne sont proposées que si la médiathèque est raccordée. Dans une collection (cartes, FAQ, informations, chiffres), enregistrer les champs avant d’ajouter, déplacer ou retirer un élément. Le maximum est de 50 éléments par collection. Les types texte et rich_text conservent les retours à la ligne mais échappent le HTML : il ne s’agit pas d’un éditeur HTML libre. Les contenus externes acceptent les adresses d’intégration HTTPS YouTube sans cookies, Vimeo et OpenStreetMap et restent bloqués avant consentement.

## Restaurer sur un autre hébergement

1. Sur une copie isolée, déployer la même version du code et installer ses dépendances.
2. Créer une base dédiée vide, puis y importer le dump SQL complet avec le client MariaDB.
3. Restaurer ensemble `config/local.php` et `storage/`, hors du répertoire public. Restreindre `local.php` à son propriétaire (0600).
4. Adapter DB_DSN, DB_USERNAME, DB_PASSWORD, APP_BASE_URL, MEDIA_PRIVATE_ROOT, MEDIA_PUBLIC_ENDPOINT et FORUM_PRIVATE_ROOT aux nouveaux emplacements ; conserver DENDRILA_AUTH_KEY. Si des variables serveur existent, les adapter également.
5. Vérifier accueil, pages publiées, invisibilité des brouillons, images, TOTP et comptes désactivés avant toute bascule. Ne pas lancer l’assistant d’installation sur cette restauration.

La CI exécute un dump MariaDB et une archive des fichiers privés de test, les restaure dans une autre base et un autre répertoire, puis vérifie ces propriétés. Les archives privées de test ne sont pas publiées comme artifacts.

Les six thèmes exécutables et leurs ressources locales sont inclus dans le dépôt : aucun second checkout n’est nécessaire sur l’hébergement. Les aperçus d’installation et les pages publiques utilisent le même thème. Voir `docs/DESIGN-STYLES.md` pour les correspondances, l’identité de marque et la mise à jour versionnée des thèmes.
