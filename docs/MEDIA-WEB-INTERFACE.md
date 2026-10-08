# Interface web de la médiathèque

## Accès

L’interface légère est servie depuis `public/admin/media-library/`. Elle utilise exclusivement l’API MMW v1 du même déploiement et n’embarque aucun compte, mot de passe ou jeton partagé.

Le navigateur essaie d’abord un jeton appareil conservé uniquement dans `sessionStorage`. Sans session valide, il propose les méthodes déjà prévues par le protocole : identifiant/mot de passe avec TOTP, ou code d’association. Le point d’entrée actuel `public/index.php` ne raccorde pas encore de `AuthenticationService` ni de `IdentityProvider`. Les routes de connexion répondent donc 503 et l’interface reste fermée sans jeton déjà émis. Le raccordement d’une identité réelle exige un lot serveur ; un simple réglage o2switch ne suffit pas.

Le mot de passe et le code TOTP ne sont jamais persistés. Le jeton appareil n’est jamais placé dans l’URL.

## Bibliothèque

L’écran propose deux présentations, grille et liste, utilisables au clavier. La recherche et les filtres sont appliqués par le serveur sur l’ensemble de la bibliothèque :

- recherche dans titre, nom de fichier, description alternative et crédit ;
- format JPEG, PNG ou WebP ;
- usage `used` / `unused` lorsque l’acteur possède aussi `content.read_draft` ;
- description alternative manquante ou présente ;
- plage de dates UTC ;
- taille minimale et maximale ;
- tri global par date, taille ou titre, ascendant ou descendant ;
- pagination avec total et indication `has_more`.

`GET /v1/media` accepte en plus le filtre exact `id`, utilisé uniquement pour relire un asset après un conflit de révision. Le snapshot MMW reste inchangé et limité à ses 100 premiers éléments.

## Édition

Le panneau d’édition affiche les mesures techniques en lecture seule et ne rend modifiables que les champs déclarés dans `module.fields`. La configuration expose explicitement `options.allow_update`, `allow_rotate` et `allow_delete`; un bouton non autorisé n’est pas présenté.

Chaque sauvegarde, rotation et suppression envoyée par cette interface contient `expected_revision`. Après HTTP 409 `conflict`, les champs saisis restent en place. L’interface relit la version serveur et demande explicitement de choisir entre :

- adopter les valeurs du serveur ;
- conserver les saisies locales sur la nouvelle révision puis enregistrer manuellement.

Aucun retry automatique ne remplace la révision attendue. Une nouvelle décision utilisateur emploie un nouvel identifiant de requête. En cas de panne réseau où le résultat est inconnu, le même identifiant est conservé pour rendre le retry idempotent.

`media_in_use` est présenté séparément d’un conflit de concurrence : le serveur ne divulgue toujours aucun titre ou identifiant de contenu privé.

## Ajout d’image

Le fichier est choisi avant l’affichage des métadonnées. L’interface applique les limites MIME/taille annoncées dans `module.media`, puis affiche uniquement les champs définis par `module.media.fields`.

L’envoi utilise `POST /v1/media` en multipart avec progression visible. Les valeurs saisies et l’identifiant idempotent sont conservés après une panne réseau ou une erreur serveur indéterminée. Ils ne sont effacés qu’après réussite ou après une nouvelle décision de l’utilisateur.

Les médias ne sont jamais mis en file hors connexion.

## Accessibilité

- structure HTML sémantique et lien d’évitement ;
- focus visible ;
- cartes actionnables par bouton natif ;
- modales basées sur `<dialog>` avec fermeture clavier native et retour de focus explicite ;
- messages d’état dans des régions `aria-live` ;
- libellés associés aux champs ;
- interface responsive téléphone/ordinateur ;
- réduction des animations avec `prefers-reduced-motion`.

Les vignettes de la bibliothèque sont décoratives dans leur carte, car le titre de l’asset est déjà annoncé par le bouton.

## Vérifications automatiques

`tests/media-web-ui.js` vérifie le client API et les transitions de conflit. La CI exécute également `node --check` sur les deux fichiers JavaScript. Les contrats MariaDB restent dans `tests/media-pdo-run.php`, y compris tri global, filtres, pagination, relecture exacte et capacité `allow_update`.

Les tests automatisés ne remplacent pas la vérification finale sur l’hébergement réel : navigateur téléphone/ordinateur, clavier, focus de modale, PHP/GD/WebP, stockage privé et configuration d’identité.

## Protection des interactions asynchrones

Une recherche ancienne arrivée en retard ne remplace plus les résultats de la recherche suivante. Une déconnexion invalide également les lectures encore en vol.

Pendant une mutation ou un transfert, le formulaire concerné et sa fermeture sont bloqués jusqu’à la réponse. Cela évite de perdre une saisie effectuée pendant l’enregistrement ou de remplacer le fichier pendant son envoi. Après un échec réseau, les champs et le fichier restent disponibles pour réessayer avec le même identifiant. La rotation demande d’abord d’enregistrer les métadonnées modifiées ; un conflit exige une décision explicite avant une nouvelle action.

Le test navigateur optionnel `node tests/media-web-browser.cjs` exige Playwright et Chromium installés dans l’environnement de test. Il utilise une API simulée sans compte réel et vérifie ces interactions, le retour de focus et l’absence de débordement horizontal à 390 et 1280 pixels. Il ne valide pas l’identité réelle ni l’hébergement. Les contrats légers restent exécutés par la CI existante.

## Connexion réelle du site pilote

Le site pilote retenu pour ce raccord est **Vues Imprenables**, pas Matercéleste. Vues Imprenables possède déjà ses comptes, son second facteur web par e-mail, ses codes d’association MMW à usage unique et sa liste d’appareils révocables.

Dendrila fournit donc un pont de migration `legacy_mmw_pairing` plutôt qu’un second système de comptes. En mode pilote :

- `site_admins` reste la source de l’acteur et du rôle ;
- `mmw_pair_codes` reste la source des codes de 8 chiffres ;
- `mmw_devices` reste la liste des appareils et la source de révocation ;
- Dendrila synchronise uniquement la membership et le rôle explicitement mappé ;
- `auth/start` et `auth/verify` restent refusés pour ce pont ;
- le manifeste Vues Imprenables annonce déjà `pairing_code`, donc l’interface masque automatiquement le formulaire mot de passe et ne présente que l’association.

La migration `009_auth_rate_limits.sql` borne les échecs d’association. Le pont conserve le HMAC historique du site pour que les codes et jetons restent compatibles avec le back-office existant. Voir [`PILOT-IDENTITY.md`](PILOT-IDENTITY.md) pour la configuration privée et le préflight.

## Prochain point : validation sur l’hébergement

Les PR #24 (socle) et #25 (interface) restent ouvertes et dépendantes ; ce lot d’identité doit également rester non déployé tant que la validation serveur n’a pas été faite.

Sur l’hébergement, appliquer les migrations puis exécuter `php bin/identity-preflight.php`. Initialiser le site Dendrila avec l’`actor_id` propriétaire existant si nécessaire, puis définir les variables privées du pont. Tester ensuite : génération d’un code depuis le back-office Vues Imprenables, association dans la médiathèque Dendrila, accès média, révocation depuis Vues Imprenables et refus immédiat du jeton révoqué.

Valider dans le même passage PHP 8.5, MariaDB, GD/WebP, limites d’envoi, permissions du stockage privé, maintenance et sauvegarde/restauration. Ne pas remplacer l’API MMW historique, le site public Vues Imprenables ni les autres outils en production pendant cette validation.
