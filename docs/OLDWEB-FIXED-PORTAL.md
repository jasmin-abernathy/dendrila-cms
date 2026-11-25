# Canal 57 · portail Oldweb

Canal 57 est le premier thème fourni avec Dendrila Retro. Le front est en HTML 4.01 Strict et
CSS 2 à largeur fixe de 820 px. La page conserve ses colonnes sur petit écran.
L’éditeur n’a besoin de JavaScript pour aucune opération.

## Installer le ZIP

Dendrila Retro s’installe sur un hébergement qui exécute **PHP 8.4 ou plus
récent**, avec **HTTPS**, les extensions PHP **fileinfo**, **mbstring** et
**session**, et un dossier privé où PHP peut lire et écrire. Le ZIP embarque
le nécessaire pour Retro, sans base de données ni installation Composer.

### Le parcours commun

1. Télécharger [Dendrila_Retro.zip](../downloads/Dendrila_Retro.zip).
2. Déposer puis extraire le ZIP dans le **dossier public du domaine**. Il
   crée un dossier `dendrila-retro`. Si le gestionnaire de fichiers ne sait
   pas extraire un ZIP, le décompresser sur son ordinateur puis envoyer
   le dossier `dendrila-retro` complet par **SFTP** ou par le moyen proposé
   par l’hébergeur. Conserver les fichiers cachés, dont `retro/.htaccess`.
3. Ouvrir `https://ton-domaine.fr/dendrila-retro/` avec son vrai domaine. Choisir
   une clé dans l’assistant puis cliquer sur **Télécharger le fichier de clé**.
4. Envoyer ce petit fichier, en gardant son nom, dans le **dossier personnel
   privé** de l’hébergement. Ce dossier contient le dossier public ou l’un de
   ses parents. Il doit être hors des dossiers publiés par les domaines du
   compte. Voir les exemples ci-dessous si le panneau n’affiche aucun
   dossier nommé `public_html`.
5. Revenir à l’assistant, cliquer sur **J’ai téléversé le fichier, continuer**,
   puis saisir la clé, le nom du site et le mot de passe souhaité.

Le petit fichier prouve que tu peux ajouter un fichier sur l’hébergement.
Il contient une empreinte liée à l’installation. Tu peux le retirer après
avoir terminé. Retire aussi le ZIP du dossier public après extraction.

Le site est à `/dendrila-retro/` et son éditeur à `/dendrila-retro/oldweb-editor.php`.
Si ton domaine ou sous-domaine pointe déjà sur le dossier `dendrila-retro`
extrait, ouvre directement `https://ton-domaine.fr/`.

### Où déposer le ZIP et le fichier de clé

| Type d’hébergement | Déposer le site | Déposer le fichier de clé et régler PHP |
| --- | --- | --- |
| **Mutualisé avec cPanel**, dont o2switch | Dans **Gestionnaire de fichiers**, ouvrir le dossier associé au domaine. Pour le domaine principal, c’est souvent `public_html`. Utiliser **Téléverser**, puis **Extraire**. | Revenir au dossier personnel du compte, en amont de `public_html` et des dossiers des domaines. Au besoin, régler PHP via **Sélectionner une version de PHP** ou l’outil équivalent du compte. [Fichiers et archives](https://faq.o2switch.fr/cpanel/fichiers/gestionnaire-fichiers-web/) · [Sélection PHP](https://faq.o2switch.fr/guides/php/heberger-application-symfony/) |
| **Mutualisé avec Plesk** | Dans **Sites Web et Domaines**, ouvrir le **Gestionnaire de fichiers** du domaine. Utiliser la racine indiquée dans **Paramètres d’hébergement**, souvent `httpdocs`. | Déposer le fichier dans le dossier privé de l’abonnement reconnu par PHP, en amont de la racine Web. Si l’assistant ne le trouve pas, voir [le réglage du dossier privé](#si-lassistant-ne-trouve-pas-le-fichier). [Documentation Plesk](https://docs.plesk.com/fr-FR/obsidian/quick-start-guide/explication-du-fonctionnement-de-plesk/g%C3%A9rer-l%E2%80%99h%C3%A9bergement-web.74401/) |
| **Mutualisé avec hPanel ou autre panneau**, dont Hostinger | Ouvrir le gestionnaire de fichiers côté **Hébergement** ou **Sites web**, puis la racine du domaine. Elle peut s’appeler `public_html` ou avoir un nom personnalisé. | Si l’interface n’affiche que le dossier public, ouvrir la vue de tous les fichiers du compte ou un accès SFTP plus large si l’offre le permet. Utiliser le sélecteur PHP du site. [Repères hPanel](https://www.hostinger.com/fr/support/4047803-comment-changer-la-version-de-php-pour-les-sous-dossiers-ou-les-sous-domaines-chez-hostinger/) |
| **OVHcloud Hébergement Web** | Relever la racine associée au domaine dans l’espace de gestion, puis y envoyer le dossier extrait via l’accès FTP/SFTP fourni. | Déposer le fichier de clé dans le dossier personnel de l’hébergement, en amont du dossier public, s’il est accessible avec ce compte. Vérifier la version PHP applicable au domaine. [Configuration PHP chez OVHcloud](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/configure-your-web-hosting) |
| **Infomaniak Hébergement Web** | Repérer le dossier cible du site dans le **Manager** et y envoyer le dossier extrait par SFTP. Le chemin peut être `web` ou celui choisi lors de la création du site. | Déposer le fichier de clé dans le dossier privé reconnu par PHP, en amont du dossier du site. Vérifier la version PHP du site dans le Manager. [Dossier cible](https://www.infomaniak.com/fr/support/faq/963/modifier-le-dossier-dun-site) · [Version PHP](https://www.infomaniak.com/fr/support/faq/2053/modifier-la-version-php-utilisee-par-un-site-web) |
| **Hébergement PHP sans gestionnaire de fichiers** | Décompresser le ZIP sur son ordinateur, puis envoyer `dendrila-retro` dans le dossier public avec un client SFTP ou le moyen fourni. | Transférer ensuite le fichier de clé, avec le même outil, dans le dossier personnel reconnu par PHP. Si le compte ne voit que la racine Web, passer par le support. |

### VPS, serveur dédié ou serveur de test

Configurer Apache, Nginx ou ton serveur PHP existant pour servir la racine
Web du domaine par HTTPS. Un exemple de disposition est
`/srv/dendrila/www` comme racine publique, avec le dossier extrait
`/srv/dendrila/www/dendrila-retro` et le fichier de clé dans
`/srv/dendrila`. Ce dernier dossier correspond à `HOME` pour PHP dans
cet exemple. PHP doit pouvoir y lire le fichier de clé et créer le dossier
privé de l’instance. Garde `/srv/dendrila` hors des racines Web et des
alias configurés pour les autres sites.

- Avec **PHP-FPM**, dans le pool du site, définis `env[HOME] = /srv/dendrila`
  si le processus n’a pas déjà le bon dossier personnel. Pour Nginx,
  assure-toi que PHP reçoit la racine du domaine et le statut HTTPS. Si
  Nginx termine lui-même TLS, `fastcgi_param HTTPS $https if_not_empty;`
  transmet ce statut. Derrière un proxy, règle-le via le proxy de confiance
  et la configuration du serveur. [PHP-FPM](https://www.php.net/manual/fr/install.fpm.configuration.php) · [Paramètres FastCGI](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html)
- Avec **Apache**, sur une configuration compatible, `SetEnv HOME /srv/dendrila`
  dans le site virtuel permet de fournir `HOME` aux scripts. Si les fichiers
  `.htaccess` sont ignorés, configure la même protection dans le serveur
  en refusant l’accès HTTP au sous-dossier `dendrila-retro/retro/`. Sur
  **Nginx**, configure ce refus dans le site virtuel. La page
  `/dendrila-retro/retro/portal-content.json` doit répondre **403** ou
  **404**. [Directive Apache SetEnv](https://httpd.apache.org/docs/current/fr/mod/mod_env.html#setenv)
- En **local**, utilise la même disposition et une adresse HTTPS de
  développement. Le serveur PHP en HTTP seul permet de vérifier le
  chargement des fichiers, mais l’assistant et l’éditeur exigent HTTPS.

### Docker et plateformes de conteneurs

Utilise un conteneur qui exécute PHP 8.4 ou plus récent avec les extensions
requises et sert le dossier Web par HTTPS, directement ou via un proxy
de confiance. Configure un chemin stable dans le conteneur, par exemple
`HOME=/srv/dendrila` et racine Web `/srv/dendrila/www`. Monte un volume
persistant pour conserver le dossier de données privé que Retro crée dans
`HOME`, en dehors de `www`. Dépose le fichier de clé dans ce même
`HOME` via un montage de fichier ou l’accès d’administration. Vérifie que
PHP peut écrire dans le volume et que le chemin de l’application reste
identique d’un déploiement à l’autre. [Volumes Docker](https://docs.docker.com/engine/storage/volumes/)

### Hébergements sans accès suffisant

- **Sites statiques.** Une offre de fichiers statiques comme GitHub Pages
  ne peut pas exécuter le ZIP. Le code PHP est nécessaire pour l’assistant
  et l’éditeur. [Documentation GitHub Pages](https://docs.github.com/fr/pages/getting-started-with-github-pages/creating-a-github-pages-site)
- **Offres WordPress gérées et services de création de sites fermés.**
  Vérifie si l’offre permet d’exécuter une application PHP indépendante
  et d’accéder à un dossier privé. Retro est une application autonome,
  aucune installation ne se fait depuis le menu Extensions de WordPress.
- **Services sans disque persistant.** Un environnement dont les fichiers
  disparaissent au redémarrage ne peut pas conserver le site. Utilise une
  offre PHP qui fournit aussi un stockage privé persistant.

### Si l’assistant ne trouve pas le fichier

Vérifie que le nom téléchargé est intact et que le fichier se trouve dans
le dossier **personnel**, en amont du dossier Web. L’interface du ZIP
emploie le mot « cPanel », y compris sur un autre panneau. C’est l’emplacement
vu par PHP qui compte.

Le ZIP repère automatiquement le dossier personnel quand la racine Web se
trouve sous `/home/compte/`, `/home1/compte/` ou un chemin équivalent.
Ailleurs, il utilise la variable `HOME` de PHP. Si le compte ne te permet pas
de voir ce dossier ou si PHP ne peut pas l’ouvrir, voici quoi demander à l’hébergeur.

> Quel est le dossier personnel de mon compte qui contient le dossier public
> de mon domaine, tout en restant inaccessible depuis le Web ? Est-ce que
> PHP peut y lire un fichier et y créer un dossier privé ? Si le chemin du
> site ne commence pas par `/home/mon-compte/`, pouvez-vous transmettre le
> chemin du dossier personnel à PHP dans sa variable `HOME` ?

Si PHP a une restriction de chemins (`open_basedir`), le support doit
autoriser l’accès à ce dossier privé pour l’application. Celui-ci doit
toujours rester inaccessible depuis le Web.

### Mise à jour et déménagement

Pour une mise à jour, extraire les nouveaux fichiers **au même emplacement**
et sauvegarder le dossier de données privé. L’archive commune ne fournit
pas de fichier `retro/installation.php`. Conserver celui d’un ancien kit
personnel s’il existe. Avec le ZIP commun, le chemin du dossier applicatif
sert à retrouver les données. Si ce chemin change lors d’un déménagement,
fais relier la sauvegarde au nouvel emplacement avant la remise en ligne.

Le ZIP se reconstruit avec `python3 bin/package-retro.py Dendrila_Retro.zip`.

## Installation avancée dans un Dendrila existant

1. Déposer un checkout Dendrila avec son répertoire `vendor/` généré par
   `composer install --no-dev`. L’hébergement doit utiliser PHP 8.5 ou plus,
   avec `fileinfo`, `mbstring` et `session`. Faire pointer la racine
   Web sur `public/`. On peut préparer `vendor/` ailleurs avant de transférer
   les fichiers sur un hébergement sans Composer.
2. Configurer un répertoire **dédié par instance**, accessible en lecture et
   écriture au processus PHP, hors de la racine Web et de ses alias. Renseigner
   son chemin absolu dans `DENDRILA_OLDWEB_PORTAL_DIR`.
3. Protéger `oldweb-editor.php` avec l’authentification HTTPS du serveur Web.
   Le serveur doit transmettre l’identité authentifiée par `REMOTE_USER`
   et marquer HTTPS dans `HTTPS`. Si PHP est derrière un proxy, traduire le
   statut TLS et l’identité dans la configuration de confiance du serveur.
   Ajouter les comptes éditoriaux à `DENDRILA_OLDWEB_PORTAL_EDITORS`
   (noms séparés par des espaces). Les requêtes dépourvues de cette identité
   autorisée sont refusées. `oldweb-portal.php` reste public.
4. Ouvrir `oldweb-editor.php` en HTTPS et se connecter. Au premier accès,
   saisir le nom du site, son sous-titre et le thème, puis cliquer sur
   **Installer Dendrila Retro**. L’assistant initialise les réglages dans le
   répertoire privé. Les visites suivantes ouvrent l’éditeur.
5. Si besoin, adapter le texte de démonstration avec un `content.json` privé.
   Le fichier [exemple](../examples/oldweb/portal-content.json) documente les
   clés disponibles. Ce fichier facultatif a priorité sur le nom et le
   sous-titre saisis dans l’assistant.
6. Régler `upload_max_filesize` à au moins 1 Mio, `post_max_size` à au moins
   8 Mio et `max_file_uploads` à au moins 6 pour accepter tous les champs d’un
   formulaire. Les limites applicatives restent à 1 Mio par fichier,
   1 600 px par côté et 32 Mio par instance.

Dendrila Retro utilise des fichiers et ne demande aucune base SQL dans cet
assistant. Les installations ayant déjà enregistré une version conservent
leurs données et accèdent directement à l’éditeur. Un deuxième passage par
le formulaire d’installation ne peut pas réinitialiser l’instance.

Les mainteneurs peuvent ajouter des thèmes au registre. Voir le
[guide de création de thèmes](OLDWEB-THEMES.md).

Les points d’entrée sont autonomes. L’API MMW, les réglages et les contenus des
sites existants continuent de passer par leurs routes habituelles.

## Personnaliser

Chaque zone expose une couleur de fond, une couleur de texte et un choix de
bordures CSS 2. Le bandeau, l’ouverture, les actualités, l’agenda, les archives
et la colonne de droite acceptent chacun une illustration. Les GIF, PNG et JPEG
sont présentés dans les limites du cadre, avec leurs proportions. Le visiteur
peut masquer les GIF depuis le pied de page.

L’éditeur contrôle les types, dimensions, tailles, valeurs CSS et contrastes
côté serveur. Les images reçoivent un nom calculé et restent dans le stockage
privé. Le point d’entrée public ne sert que les médias de la version publiée.
Le brouillon et ses médias restent accessibles aux éditeurs authentifiés.
Les mutations exigent un jeton de session et une révision courante. Enregistrer,
publier et rétablir sont des actions distinctes. Les écritures sont sérialisées
et la mise à jour du fichier de réglages est atomique.

Les originaux importés restent dans le répertoire privé afin de conserver les
GIF et leurs images successives. Lors de la maintenance du quota, conserver
les fichiers `portal-*` référencés par `draft`, `published` et `previous`
dans `appearance.json`. Faire cette opération serveur en dehors des périodes
d’édition, après sauvegarde.
