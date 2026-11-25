# Dendrila Retro · Canal 57, Borne 02 et Signal 96

Dendrila Retro fournit Canal 57 (820 px), Borne 02 (800 px) et Signal 96 (780 px), trois thèmes
à largeur fixe. Canal 57 et Borne 02 utilisent HTML 4.01 Strict et CSS 2.
Signal 96 reprend le HTML 3.2 et le CSS 1. Les sites publiés fonctionnent
sans JavaScript.

## Installer le ZIP

Il te faut un hébergement avec **PHP 8.4 ou plus récent**, **HTTPS** et les
extensions **fileinfo**, **mbstring** et **session**. PHP doit pouvoir écrire
dans un dossier privé, hors de toutes les racines web et de leurs alias.

**Avant d’extraire le ZIP**, prépare ton installation. Tant que le site est
accessible et non initialisé, la première personne qui termine l’assistant
devient administratrice. Installe immédiatement ou protège l’URL avec
l’authentification de ton hébergeur avant l’extraction.

1. Télécharge [Dendrila_Retro.zip](../downloads/Dendrila_Retro.zip). Extrais-le
   dans le dossier public du domaine et retire l’archive du dossier public.
   Si ton panneau ne sait pas décompresser, extrais sur ton ordinateur, puis
   envoie le dossier `dendrila-retro` complet par SFTP. Garde les fichiers
   cachés, dont `retro/.htaccess`.
2. Ouvre `https://ton-domaine.fr/dendrila-retro/`. Si le domaine pointe déjà
   sur le dossier Retro, ouvre la page d’accueil du domaine.
3. Saisis le nom du site, un sous-titre si tu le souhaites, et choisis un
   thème avec l’aperçu interactif. Choisis ton identifiant, saisis ton mot de
   passe (14 caractères minimum, 72 octets maximum), confirme-le et clique sur
   **Installer Dendrila Retro**.

Le parcours standard se fait dans le navigateur, sans clé, base de données ni
Composer. Le back-office s’ouvre après l’installation. Son adresse se termine
par `/oldweb-editor.php`.

### Où déposer le ZIP

| Hébergement | Dossier et vérifications |
| --- | --- |
| **cPanel**, dont o2switch | Dans **Gestionnaire de fichiers**, ouvre le dossier du domaine. Pour le domaine principal, c’est souvent `public_html`. Utilise **Téléverser** puis **Extraire**. [Gestionnaire de fichiers](https://faq.o2switch.fr/cpanel/fichiers/gestionnaire-fichiers-web/) |
| **Plesk** | Trouve la racine dans **Sites Web et Domaines → Paramètres d’hébergement**, souvent `httpdocs`. Utilise le gestionnaire du domaine. |
| **hPanel / Hostinger** | Depuis **Sites web** ou **Hébergement**, ouvre le gestionnaire du domaine et repère son dossier public. Vérifie la version PHP dans le panneau. |
| **OVHcloud** | Relève la racine du domaine dans l’espace de gestion. Décompresse le ZIP localement et transfère le dossier complet par SFTP. [Configuration PHP](https://docs.ovhcloud.com/fr/guides/web-cloud/web-hosting/configure-your-web-hosting) |
| **Infomaniak** | Relève le dossier cible dans le Manager puis transfère le dossier complet par SFTP. [Dossier cible](https://www.infomaniak.com/fr/support/faq/963/modifier-le-dossier-dun-site) |
| **Autre hébergement PHP** | Décompresse le ZIP et envoie le dossier complet dans la racine du domaine. Vérifie le stockage persistant privé avant d’exposer l’URL. |

### VPS, serveur dédié et conteneurs

Utilise un chemin stable, par exemple `/srv/dendrila/www` pour la racine
publique et `/srv/dendrila/private` pour le stockage de l’instance. Le
dossier privé doit exister, être accessible en écriture à PHP et rester
hors de toutes les racines web. En PHP-FPM, passe son chemin au pool via
`env[DENDRILA_RETRO_PRIVATE_HOME]`. Pour un conteneur, monte un volume
persistant à cet emplacement et configure la même variable.

Bloque l’accès HTTP à `dendrila-retro/retro/` dans le site virtuel si les
fichiers `.htaccess` sont ignorés. Garde le dossier de données et le fichier
de preuve hors des alias. Transmets le statut HTTPS à PHP depuis le serveur
ou le proxy de confiance. N’utilise pas un en-tête transmis par un client
comme preuve HTTPS. Un hébergement statique ou sans stockage persistant ne
peut pas installer Retro.

### Si l’assistant ne trouve pas de dossier privé

Retro peut repérer le dossier personnel sous `/home/compte/` ou utiliser
le `HOME` reconnu par PHP si la racine du site en dépend. Demande à ton
hébergeur un dossier hors de toutes les racines web dans lequel PHP peut
lire et écrire. Si nécessaire, demande-lui de renseigner
`DENDRILA_RETRO_PRIVATE_HOME` et d’autoriser le chemin dans `open_basedir`.
Pour une mise à jour, conserve le dossier de données déjà utilisé.

### Installation protégée facultative

Pour activer la clé **avant d’ouvrir l’URL au public**, configure
`DENDRILA_RETRO_REQUIRE_KEY=1` côté serveur ou utilise un kit personnel
construit avec `python3 bin/package-retro.py kit-prive.zip --personal`.
Avec ce kit, déploie uniquement `Site_a_envoyer.zip` et garde le fichier
`LIRE-MOI.txt` qui contient la clé sur ton ordinateur.

En mode protégé par l’hébergeur, l’assistant permet de choisir une clé et
de télécharger son empreinte. Place le fichier `dendrila-cle-…txt` sans
le renommer dans le dossier privé reconnu par PHP, puis saisis la clé
d’origine dans l’assistant. Un fichier ancien encore présent maintient
l’instance en mode protégé. S’il est illisible ou invalide, le mode reste
verrouillé jusqu’à son remplacement par une preuve valide. Les anciens
kits personnels restent protégés par leur clé.

### Mise à jour et déménagement

Pour une mise à jour, extraire les nouveaux fichiers **au même emplacement**
et sauvegarder le dossier de données privé. L’archive commune ne fournit
pas de fichier `retro/installation.php`. Conserver celui d’un ancien kit
personnel s’il existe. Avec le ZIP commun, le chemin du dossier applicatif
sert à retrouver les données. Si ce chemin change lors d’un déménagement,
fais relier la sauvegarde au nouvel emplacement avant la remise en ligne.

Les comptes déjà installés sans identifiant continuent à utiliser leur mot
de passe seul. Une installation ancienne peut contenir les exemples
`retro/*-content.json`. Après sauvegarde, retire ces anciens exemples lors de
la mise à jour. Le ZIP actuel les livre sous forme de fichiers PHP protégés.

### Reproduire l’archive et les démonstrations

Pour reconstruire l’archive, les démos et les trois vignettes sur un même SHA,
lance `bin/release-retro.sh DOSSIER SHA_GIT` dans un checkout propre. PHP 8.4+,
Playwright avec Chromium, OpenSSL et Python 3.10+ avec Pillow sont nécessaires
pour la reproduction des captures. Les variables `PHP_BIN`,
`PLAYWRIGHT_MODULE` et `CHROMIUM_BIN` peuvent préciser les outils.

Pour exécuter les étapes séparément :

1. Construis l’archive avec `python3 bin/package-retro.py Dendrila_Retro.zip`.
2. Exporte le contenu depuis cette archive avec
   `python3 bin/export-retro-demos.py Dendrila_Retro.zip DOSSIER --source SHA_GIT`.
   Le manifeste contient la provenance, les empreintes des ressources et
   le SHA-256 de l’archive.
3. Exécute `node tests/retro-install-run.cjs Dendrila_Retro.zip CAPTURES` avec
   PHP, Chromium/Playwright, Python/Pillow et OpenSSL disponibles. Le test
   installe chaque thème en HTTPS local, vérifie la parité et génère les
   vignettes WebP pour la vitrine.

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

**Police générale** change la police de base du site. Chaque bloc possède
aussi une liste **Police** avec des familles comme Arial, Verdana, Tahoma,
Courier New ou Comic Sans MS. **Police du thème** rend au bloc son réglage
d’origine. Ces choix utilisent les familles installées sur l’appareil du
visiteur et une famille de repli si besoin. Aucun fichier de police ne se
charge à l’ouverture du site.

Choisis **Canal 57**, **Borne 02** ou **Signal 96** dans **Thème du portail**. Pour reprendre
les couleurs et bordures d’origine du thème choisi, coche la case
correspondante avant d’enregistrer le brouillon. Les couleurs existantes
sont conservées par défaut lors d’un changement de thème et la case n’affecte
pas les images. Prévisualise le brouillon avant de le publier.
Les illustrations de [Borne 02](../public/assets/borne-02/licences/illustrations.txt) et de
[Signal 96](../public/assets/signal-96/illustrations.txt) sont en CC0.

Chaque zone expose une couleur de fond, une couleur de texte et un choix de
bordures CSS 2. Le bandeau, l’ouverture, les actualités, l’agenda, les archives
et la colonne latérale acceptent chacun une illustration. Les GIF, PNG et JPEG
sont présentés dans les limites du cadre, avec leurs proportions. Le visiteur
peut masquer les GIF via le lien « Masquer les GIF ».

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
