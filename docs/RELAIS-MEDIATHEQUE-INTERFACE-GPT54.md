# Relais — interface web de la médiathèque — GPT 5.4

## Point d'arrêt et périmètre

Le lot du 8 octobre stabilise le modèle de données et s'arrête volontairement **avant l'interface web et o2switch**.

- Dépôt : `jasmin-abernathy/dendrila-cms`.
- PR serveur : https://github.com/jasmin-abernathy/dendrila-cms/pull/24
- Branche de travail actuelle : `work/native-media-library-20261008`.
- Socle avant stabilisation : `c40817906d0601f3b4c47e34abf3896b70d87ccd`.
- Complément Android MMW : https://github.com/jasmin-abernathy/lepotager-site-manager/pull/9 (sélecteur d'ajout dans la liste existante). Aucun nouveau changement Android dans ce lot de stabilisation.

Avant de commencer : relire l'état réel des PR et leurs derniers checks. Si #24 est fusionnée, partir du `main` actualisé. Sinon, partir de son dernier head dans une branche dédiée et conserver la dépendance explicite ; ne pas repartir du `main` sans la médiathèque et ne pas écraser la branche existante.

Lire `AGENTS.md`, les règles et incidents de `repo-factory`, `docs/MEDIA-DATA-MODEL.md`, `docs/MEDIA-LIBRARY.md`, puis `docs/protocol-v1.md` dans MMW. Le contrat de données fait autorité. Conserver une mutation compatible MMW v1.

## Ce qui existe déjà — ne pas reconstruire

- Migrations `007_media.sql` (assets/variantes) et `008_media_revision.sql` (révision concurrente additive).
- Originaux privés, décodage et réencodage JPEG/PNG/WebP, dérivés thumb/web, rotations non destructives.
- `MediaService` : import, mutations, lecture filtrée, projection publique et nettoyage récupérable.
- Routes privées `POST /v1/media`, `GET /v1/media`, `POST /v1/changes`, configuration et snapshot.
- Permissions côté serveur, idempotence site/acteur/requête, blocage de suppression sur usages brouillon/publié.
- Projection lisible pour l'UI : dimensions originales distinctes des dérivés, MIME, tailles, dates UTC, `version` des pixels et `revision` de l'asset.
- `expected_revision` facultatif pour les anciens clients, à utiliser **systématiquement dans la nouvelle interface**.
- Documentation de mise en service et commandes d'activation/maintenance.

## Chantier à réaliser ensuite, en un lot cohérent

1. Construire une médiathèque web en grille/liste, responsive et utilisable au clavier.
2. Brancher recherche et filtres sur l'API existante. Compléter côté serveur les besoins manquants : tri date/taille/titre, filtres date/taille/alt manquant, pagination exploitable. Ne pas faire un tri local de 100 éléments en prétendant trier toute la bibliothèque.
3. Ajouter un panneau ou une modale accessible pour titre, alt, légende et crédit/source, sans quitter la bibliothèque. Respecter strictement les champs du module.
4. Prévoir sélection de fichier avant les champs manuels, progression d'envoi, état vide, aucun résultat, chargement, erreur réseau, refus de droits, conflit de révision et média utilisé.
5. Conserver les valeurs saisies en cas d'erreur. Après HTTP 409, proposer une relecture sans écrasement silencieux ni retry avec une révision remplacée arbitrairement.
6. Vérifier téléphone/ordinateur, clavier, focus dans la modale, retour de focus, messages accessibles et rafraîchissement après mutation. Documenter le résultat et les limites.

Aucune interface web d'administration complète n'a été construite dans ce lot. Examiner les points d'entrée et l'authentification existants avant de choisir une structure légère dans ce dépôt. Le noyau attend un adaptateur d'identité configuré par site : ne pas inventer de connexion permissive, de jeton partagé embarqué ou de page admin publique pour débloquer une démonstration. Les doubles de test doivent rester confinés aux tests.

## Appels concrets

Tous les appels privés emploient `X-Lepotager-Protocol: 1` et le jeton appareil autorisé du site. Le jeton ne doit pas apparaître dans une URL ou un log.

Lecture :

`GET /v1/media?module_id=media_library&query=arbre&mime=image/png&usage=all&limit=100&offset=0`

Filtres déjà présents : `query` (titre/nom/alt/crédit), `mime` (`image`, JPEG/PNG/WebP), `usage` (`all`, `used`, `unused`), `limit`, `offset`. Pour `used/unused`, le serveur exige aussi `content.read_draft`. Le snapshot n'inclut que les 100 premiers médias ; ne pas traiter ce sous-ensemble comme l'ensemble des données.

Mise à jour :

```json
{
  "module_id": "media_library",
  "action": "update_item",
  "client_request_id": "nouvel-identifiant-unique",
  "payload": {
    "item_id": "m_IDENTIFIANT_REEL",
    "parent_id": "media_library",
    "expected_revision": 4,
    "alt": "Description adaptée de l'image"
  }
}
```

La même structure sert à `rotate_left`, `rotate_right`, `delete_item`, sans métadonnées supplémentaires. Les options `allow_rotate`/`allow_delete` viennent du serveur et ne se renvoient pas dans le payload.

Upload multipart : `module_id=media_library`, `item_id=media_library` (contexte, pas une page), `client_request_id`, `metadata` objet JSON et fichier `media`. L'upload crée un asset sans l'insérer dans une page. Aucune file hors connexion pour les médias.

Réponses importantes : 403 refus de droits, 404 asset introuvable, 409 `media_in_use` ou `conflict`, 422 validation, 503 stockage indisponible. Ne pas masquer ces cas sous un message unique « erreur technique ».

## Vérifications à conserver

- `composer lint` et `composer test`.
- Workflow existant `Native privacy core` : PHP 8.5 et MariaDB 11.4, migrations et `tests/media-pdo-run.php`.
- Tests de régression révision/métadonnées/rotation, retry sans double incrément, refus de modification obsolète, migration rejouable sans perte de données.
- Tests interface ciblés sur les vrais parcours, pas uniquement des assertions de chaînes de code.
- Lire la conclusion du dernier SHA, pas celle du premier commit. Regrouper les changements avant push pour limiter Actions.

## Ce qui reste humain après ce chantier

La fusion et le déploiement ne sont pas réalisés par ce relais. L'étape o2switch demandera la vérification de PHP/GD/WebP, MariaDB, répertoire privé/permissions, variables d'environnement, tâche de maintenance et sauvegarde/restauration base + fichiers. Ne pas activer Materceleste publiquement ni retirer Easy!Appointments/AbanteCart.

Consigne de reprise : « Réalise uniquement le chantier d'interface décrit ici à partir du code déjà présent, documente les résultats et arrête-toi avant le déploiement o2switch ou dès qu'un vrai relais d'authentification/configuration humaine est nécessaire. »
