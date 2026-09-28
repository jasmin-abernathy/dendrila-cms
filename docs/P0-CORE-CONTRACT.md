# Contrat P0 du CMS Core

Statut : **spécification de départ**, à valider par les tests de contrat avant toute annonce de stabilité.

Ce document fixe la frontière entre le noyau de contenu, le stockage, l'autorisation, le workflow éditorial et l'adaptateur vers le protocole MMW v1. Il n'impose pas encore de langage ni de framework serveur.

## 1. Responsabilités

Le CMS Core fournit des capacités serveur génériques :

- types et instances de contenu ;
- brouillons, revue et publication ;
- rôles et autorisations ;
- historique immuable et restauration ;
- lectures/écritures transactionnelles et idempotentes ;
- projections vers les primitives MMW déjà comprises par les clients.

Il ne remplace pas l'authentification MMW, TOTP, l'association QR/code, les jetons par appareil, ni les connecteurs vers les outils métier. L'identité authentifiée vient du serveur MMW ; le CMS applique ensuite ses propres droits sur le site et la ressource concernés.

## 2. Interfaces logiques

Les noms ci-dessous sont des contrats conceptuels, pas une API publique figée.

### `ContentTypeRegistry`

- `get(site_id, type_id)` retourne un modèle déclaré ou `NotFound`.
- `list(site_id)` retourne uniquement les modèles activés pour ce site.
- Un modèle ne contient que des champs déclaratifs et des contraintes validables. Il ne transporte aucun code exécutable.

### `ContentService`

- `get(site_id, content_id, actor)` lit une ressource après contrôle d'accès.
- `list(site_id, query, actor)` filtre par type, statut, texte, date et pagination bornée.
- `create_draft(site_id, type_id, values, actor, request_id)` crée un contenu en brouillon.
- `update_draft(site_id, content_id, expected_version, values, actor, request_id)` refuse les mises à jour périmées.
- `transition(site_id, content_id, target_status, expected_version, actor, request_id)` applique uniquement une transition permise.
- `restore_revision(site_id, content_id, revision_id, expected_version, actor, request_id)` restaure le snapshot comme une nouvelle version.

Chaque écriture valide le modèle et les valeurs côté serveur, autorise l'action, vérifie la version attendue, écrit le changement et sa révision dans une même transaction, puis enregistre la réponse idempotente.

### `AuthorizationService`

- `authorize(site_id, actor_id, action, resource)` renvoie une décision explicite autorisé/refusé.
- Refus par défaut ; aucun droit ne découle du seul fait qu'un élément soit visible dans l'interface.
- Les droits sont recalculés pour chaque lecture ou mutation sensible. Une réponse mise en cache ne vaut jamais autorisation pour une écriture.

### `RevisionStore`

- `append(revision)` ajoute une révision sans possibilité de modification/suppression par le flux applicatif normal.
- `list_for_content(site_id, content_id, cursor, limit)` retourne un historique paginé et borné.
- Une restauration ajoute une révision de restauration ; elle ne réécrit ni ne supprime les révisions précédentes.

### `TransactionRunner` et `IdempotencyStore`

- Toute mutation métier et sa révision sont atomiques.
- Une clé de requête est unique dans le périmètre `(site_id, actor_id, client_request_id)`.
- Même clé et même empreinte de requête : renvoyer la réponse déjà enregistrée, sans seconde mutation.
- Même clé avec un contenu de requête différent : conflit explicite, sans mutation.
- La réponse idempotente est validée dans la même transaction que la mutation.

### `ContentRepository`

- Toutes les clés, index et requêtes sont bornés par `site_id`.
- Les mises à jour exigent un `expected_version` ou un mécanisme équivalent de concurrence optimiste.
- Aucun accès de lecture ou d'écriture ne doit pouvoir traverser les sites par omission du contexte de site.

## 3. Modèle de contenu initial

Une instance contient au minimum :

```json
{
  "id": "opaque-stable-id",
  "site_id": "site-id",
  "type": "page",
  "slug": "presentation",
  "status": "draft",
  "values": {},
  "seo": {},
  "version": 1,
  "current_revision_id": "revision-id",
  "created_at": "date-time",
  "updated_at": "date-time",
  "published_at": null
}
```

- `id` est stable et opaque ; un slug peut changer sans changer l'identité.
- Les types initiaux sont `page`, `article`, `record` et `document`. Les kits peuvent déclarer des types réutilisables comme `service`, `event` ou `team_member` sans spécialiser l'application MMW.
- Les champs sont typés, étiquetés et validés côté serveur. Les types internes plus riches peuvent être projetés seulement si une représentation sûre et compréhensible par la version cliente ciblée existe.
- `values` contient des données structurées. Les valeurs d'affichage réservées au protocole MMW (`id`, `title`, `subtitle`, `status`) restent contrôlées par l'adaptateur ; elles ne sont pas écrasées par un champ utilisateur homonyme.
- Les éléments publiés sont servis depuis une version publiée stable. Un brouillon ultérieur ne remplace pas silencieusement le contenu public.

## 4. Rôles et décisions d'autorisation

Les rôles livrés sont des modèles modifiables par site, pas des rôles codés dans le client :

| Rôle initial | Capacité générale |
|---|---|
| `owner` | administration complète du site et des membres |
| `admin` | gestion du contenu, médias, navigation, réglages non sensibles et membres délégués |
| `editor` | création, modification, revue et publication selon la politique du site |
| `contributor` | création et modification de brouillons ; pas de publication directe par défaut |
| `viewer` | lecture seule des ressources auxquelles il est affecté |

Les permissions atomiques utilisent `domaine.action`, par exemple `content.read`, `content.create`, `content.update`, `content.publish`, `content.restore_revision`, `media.upload`, `media.delete`, `navigation.manage`, `users.manage` et `audit.read`.

La décision prend en compte le site, l'acteur, son appartenance et son rôle, la ressource, son état et l'action. Le client peut masquer une commande pour l'UX, mais seul le serveur décide. Les secrets des connecteurs ne sont jamais accessibles via un rôle éditorial.

## 5. Workflow éditorial

États P0 : `draft`, `review`, `published`, `archived`.

- `draft -> review` requiert `content.submit_for_review` ;
- `draft -> published` requiert `content.publish` et une politique autorisant la publication directe ;
- `review -> draft` requiert `content.update` ;
- `review -> published` requiert `content.publish` ;
- `published -> draft` est une dépublication explicite, autorisée et auditée ;
- `published -> archived` requiert `content.archive` ;
- `archived -> draft` requiert `content.update`.

Une transition vers `published` valide toutes les données, crée une révision, actualise atomiquement la version publiée et l'horodatage de publication. Toute transition refusée laisse l'état public et la révision courante intacts.

## 6. Révisions et restauration

Chaque révision immuable comprend au minimum : `revision_id`, `site_id`, `entity_id`, `entity_type`, `version`, `author_id` nullable pour les opérations système, `created_at`, `change_summary`, `source`, et un `snapshot` complet des champs CMS nécessaires pour reconstruire l'état.

- Sources initiales : `web`, `mmw`, `import`, `system`.
- Les numéros de version augmentent strictement pour un contenu.
- Une révision publiée et le pointeur vers la version publique changent dans une transaction.
- La restauration copie l'ancien snapshot vers un nouvel état de travail, valide les droits et le modèle, puis ajoute une nouvelle révision ; elle ne remet pas le compteur de version en arrière.
- L'historique est exportable dans un format documenté.

## 7. Adaptation au protocole MMW v1

L'adaptateur expose des primitives déjà comprises par le client, sans ajouter de logique client pour un métier particulier.

| Besoin CMS | Primitive MMW | Limite à respecter |
|---|---|---|
| Vue d'ensemble | `dashboard` | lecture d'un résumé déclaré |
| Champs d'une page/configuration | `form` + `update_fields` | types de champs v1 uniquement ; pas de bloc exécutable |
| Collections génériques | `records` | lecture structurée ; seules les actions `item_action` déclarées sont prises en charge par le contrat v1 |
| Échéances et rendez-vous | `calendar` | même règle `item_action`, avec dates ISO 8601 |
| Galerie | `gallery` | `create_item`, `update_item`, `delete_item` selon les capacités déclarées |
| Demandes | `requests` | le protocole v1 ne définit pas de CRUD éditorial générique pour ces éléments |
| Bibliothèque média mobile | `media_library` | présente dans l'application et son test de contrat ; absente de la liste des modules du document `protocol-v1.md`, donc extension implémentée à documenter avant de la déclarer contractuelle |
| Téléversement de média | `POST /v1/media` | MIME, taille, dimensions, autorisation et idempotence vérifiés côté serveur |
| SEO et navigation | champs `form` ou interface web du site | aucun nouveau `kind` n'est introduit par cette spécification |

Types de champs v1 documentés : `text`, `multiline`, `boolean`, `single_choice`, `number`, `url`, `email`. Les champs CMS `rich_text`, `blocks`, `relation` ou tout type ultérieur ne sont pas envoyés tels quels à un client qui ne les déclare pas. L'adaptateur doit fournir une projection v1 sûre ou refuser explicitement l'édition dans l'app ; il ne doit pas demander l'exécution d'un composant arbitraire.

Toutes les écritures provenant de MMW conservent `client_request_id` et la sémantique d'idempotence existante. Authentification, TOTP, pairing, jetons d'appareil, manifeste HTTPS et en-têtes de protocole restent ceux de MMW v1.

## 8. Invariants vérifiables

Les tests de contrat locaux doivent prouver que :

1. un acteur d'un site ne lit ni ne modifie un autre site ;
2. un rôle sans permission ne peut pas exécuter l'action, même en appelant directement le serveur ;
3. une édition concurrente avec version périmée échoue sans écraser les données ;
4. une requête répétée à clé identique ne crée qu'une mutation et une révision ;
5. une même clé avec un payload différent échoue ;
6. révision, état du contenu et réponse idempotente sont atomiques ;
7. les snapshots historiques restent inchangés après modification/restauration ;
8. une publication directe respecte les droits et la politique du site ;
9. l'adaptateur ne sort que les modules/types/actions déclarés compatibles avec la version MMW ciblée ;
10. aucun kit ni connecteur n'injecte du code exécutable dans l'application.

## 9. Décisions encore ouvertes avant code serveur

- langage et runtime du serveur de référence ;
- moteur de persistance initial et stratégie de migration ;
- représentation de blocs riches dans les clients actuels ;
- déclaration formelle et tests du module `media_library` dans la documentation et le schéma du protocole ;
- politique exacte de suppression/anonymisation et rétention des révisions ;
- séparation des capacités par rôle par défaut pour chaque kit.

Ne pas figer ces choix par un prototype de client ou par une API spécifique à un hébergeur.
