# Back-office commun Dendrila — première version (2026-10-28)

## Pourquoi ce module existe

L'administration historique **BMH Rénovation** est un mini-CMS PHP/SQLite séparé
(`bmh-admin`) avec TOTP, 2FA, contenu, réalisations, photos, comptes et Mon Manager
Web. **Oscura / Vues Imprenables** utilise une autre administration PHP/MariaDB :
textes par page, album photo, menu réordonnable, invitations et interface « Appli ».

Ce module rassemble **l'interface**, mais ne fusionne **pas** silencieusement leurs
bases, leurs comptes, leurs modèles de données ni leurs règles de publication.

| Fonction | BMH historique | Oscura historique | Commun Dendrila |
|---|---|---|---|
| Vue d'ensemble | Oui | Interface éditoriale | Vue commune |
| Pages/textes | SQLite/manifestes | MariaDB, pages sélectionnables | `ContentService` CMS |
| Titre sur plusieurs lignes | Selon les formulaires | Oui | Oui, `textarea` |
| Navigation | Spécifique site | Glisser-déposer | `NavigationService`, glisser-déposer/tactile/clavier |
| Photos/médias | Upload et réalisations | Albums, légendes et WebP | **Port optionnel**, pas de faux upload natif |
| Utilisateurs | Comptes et TOTP | Comptes et invitations par e-mail | **Port optionnel**, authentification site conservée |
| Application | Jetons MMW et association | Lien APK bêta et PWA iPhone | Lien Android configurable + instructions iOS |
| Publication | Directe ou revue selon site | Brouillon/publication | Workflow Dendrila déjà existant |
| Historique | Propre au mini-CMS | Champ version technique | Révisions/versionnage natifs Dendrila |

## Implémentation intégrée à `dendrila-cms`

- `src/BackOffice/Kernel.php` : routage GET/POST, permissions, CSRF, erreurs et en-têtes de sécurité ;
- `src/BackOffice/CoreData.php` : contenu et menus via **les vrais services Dendrila**,
  sans SQL spécifique client ;
- `src/BackOffice/View.php` : interface française responsive, labels non techniques,
  édition immédiate dès la sélection, et navigation tactile/clavier ;
- `src/BackOffice/Config.php` : site, identité visuelle, URLs autorisées et téléchargement Android ;
- `src/BackOffice/IdentityProvider.php` : contrat avec l'authentification réelle du site
  (après 2FA) ; le navigateur ne peut pas fournir lui-même son `actor_id` ;
- `src/BackOffice/CsrfProvider.php` et `SessionCsrf.php` : CSRF de session. La session
  **doit** être créée/vérifiée par le site avant toute utilisation ;
- `src/BackOffice/MediaPort.php` et `UsersPort.php` : interfaces facultatives
  pour médias et invitations ; tant qu'elles ne sont pas raccordées, les onglets
  concernés ne promettent pas une fonction inactive ;
- `public/backoffice-assets/` : CSS et JS communs sans CSS inline, accessibles sur le domaine hôte ;
- `examples/backoffice/bootstrap.example.php` : amorçage volontairement inactif
  sans configuration privée valide ;
- `tests/backoffice-run.php` : contrats de sécurité, modification des pages,
  verrouillage optimiste des versions, menu, adaptation mobile et PWA.

Les identifiants internes de contenu, versions et champs ne sont pas affichés comme
des numéros de classement. Le menu utilise la structure Dendrila et protège
`/` contre une désactivation involontaire.

## Installation sur un **nouveau** site Dendrila

1. Utiliser un environnement Dendrila compatible **PHP 8.5+**, MariaDB et
   les migrations existantes. **BMH ne remplit pas encore cette condition sans migration**.
2. Installer le moteur Dendrila en dehors du webroot et placer les fichiers
   de `public/backoffice-assets` à l'adresse publique choisie dans `assetsPath`.
3. Définir **hors webroot** l'amorçage local du site, le `PDO`, le `siteId`
   et surtout un `IdentityProvider` qui **vérifie réellement mot de passe,
   session et second facteur** ; ne jamais accepter un acteur depuis GET/POST,
   un header libre ou un simple cookie.
4. Raccorder `Kernel` selon `examples/backoffice/bootstrap.example.php`.
   Définir `basePath` (ex. `/espace-client/`) et l'URL de connexion adaptée.
5. Activer éventuellement `MediaPort` et `UsersPort` après implémentation
   des permissions, taille maximale, MIME, réencodage, stockage **hors webroot**,
   invitations sans mot de passe et journalisation.
6. Tester avec un **vrai compte** propriétaire/éditeur et un utilisateur de
   lecture seule, puis régressions de session/2FA, médias, workflow et publication.

Le manifeste iPhone est servi à `<basePath>?manifest=1` et son style thématisé
à `<basePath>?theme=1`. L'icône provient de `iconUrl`. Aucun service worker
n'est installé et aucune page privée n'est mise en cache hors ligne.

## Transition sûre des sites existants

**BMH :** garder `bmh-admin`, sa base SQLite, ses comptes TOTP, ses secrets
privés et ses scripts de déploiement tant qu'un adaptateur identité,
un import du contenu, un adaptateur médias et les tests de non-régression
n'ont pas été faits. La politique de publication existante doit être vérifiée
avec la cliente, elle n'est **pas** remplacée par défaut.

**Oscura :** garder son back-office en production, la base actuelle, les
galeries et le forum en service. Préparer un mapping des types/pages,
la migration des fichiers photo, des rôles et des droits de modération vers
le noyau. La migration des anciennes archives SPIP est **un projet distinct**.

### Ce que la V1 fait vraiment

**Fonctionnel et testé en isolation :** consultation/édition des pages de type
`page` pour les champs texte déclarés (en conservant les autres champs),
vérification de version, création d'un brouillon lors de l'édition d'un contenu
publié, action distincte de publication, navigation et permissions natives,
formulaires CSRF, UI responsive, PWA iPhone et lien Android configuré.

**Non finalisé :** éditeur complet des blocs structurés, moteur média natif,
invitation/2FA universelle, migration automatique BMH/Oscura et publication
du module sur un domaine client. Ces fonctions sont prévues derrière des
**interfaces explicites** pour ne pas entretenir de fausses fonctionnalités.

**Aucune bascule de domaine, aucune mutation des bases clients n'est lancée
par cette branche.**
