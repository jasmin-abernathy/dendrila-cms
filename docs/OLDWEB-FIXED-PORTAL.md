# Canal 57 · portail Oldweb

Canal 57 est le premier thème fourni avec Dendrila Retro. Le front est en HTML 4.01 Strict et
CSS 2 à largeur fixe de 820 px. La page conserve ses colonnes sur petit écran.
L’éditeur n’a besoin de JavaScript pour aucune opération.

## Installer depuis le navigateur

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
