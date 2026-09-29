# Intégration avec Mon Manager Web

Le CMS Core doit alimenter ou implémenter le protocole existant.
Référence : `jasmin-abernathy/lepotager-site-manager/docs/protocol-v1.md`.

## Mapping initial

- contenu simple -> `form`
- collections -> `records`
- agenda -> `calendar`
- galerie -> `gallery`
- bibliothèque média mobile -> `media_library` (prise en charge dans l'application MMW et un test de contrat, mais pas encore listée dans `docs/protocol-v1.md`)
- demandes -> `requests`

Les opérations observées dans l'app et le test `MediaLibraryContractTest` sont `update_item`, `rotate_left`, `rotate_right` et `delete_item`. Elles restent une extension provisoire tant que le protocole et son schéma ne les documentent pas. L'API serveur ne doit pas supposer qu'un ancien client les comprend.

Le protocole v1 documente les champs `text`, `multiline`, `boolean`, `single_choice`, `number`, `url` et `email`. Les champs internes CMS comme `rich_text`, `blocks` ou `relation` doivent être projetés de façon sûre ou rester indisponibles dans le client ; ils ne deviennent pas automatiquement de nouveaux types v1.

Les mutations génériques v1 sont limitées à `update_fields` pour `form`, `create_item`/`update_item`/`delete_item` pour `gallery`, et `item_action` déclaré pour `records`/`calendar`. Le serveur revérifie droits, état de l'objet et idempotence dans tous les cas.

La configuration privée du site porte `config_version` (branding, politique, modules, champs et actions) ; `snapshot.revision` décrit l'évolution des données. Les deux sont distincts des versions de révision par contenu du CMS. La politique `review_before_publish` peut imposer l'étape de revue ; le serveur renvoie alors le résultat MMW prévu (`pending_review`) au lieu d'annoncer une publication directe (`applied`).

La file hors ligne MMW est limitée aux petites mutations JSON explicitement éligibles par l'action (`allow_offline`) et par `policy.allow_offline_queue`. Les uploads, binaires média, suppressions risquées et actions exigeant une approbation ou une transaction ne sont pas mis en file. Les erreurs de validation et de protocole ne sont pas des erreurs réseau et ne doivent pas être mises en attente. `requests` reste un module de demande métier, pas un CRUD éditorial implicite ; `records` et `calendar` n'exposent que les actions déclarées par le serveur.

Avant d'ajouter un nouveau `kind`, vérifier qu'aucune primitive existante ne suffit et que le besoin est réutilisable par plusieurs intégrations. Le contrat P0 complet et les limites actuelles figurent dans [`P0-CORE-CONTRACT.md`](P0-CORE-CONTRACT.md).


## Couche HTTP et authentification MMW v1

Le routeur PHP expose maintenant GET /v1/config et GET /v1/snapshot. Les deux routes exigent les en-têtes de protocole v1 et un jeton appareil résolu côté serveur vers une appartenance de site. Les contrôles HTTP ne prennent jamais site_id ou actor_id depuis un payload client. Le snapshot transmet l'acteur au `ContentService` : les droits de lecture et la visibilité des brouillons sont donc réévalués côté serveur.

La migration 002_api_devices_and_settings.sql ajoute les appareils et les réglages de configuration API. Une définition de module peut associer ses champs à un contenu ou à un type de contenu CMS avec `source` et `source_field_id`. Ces clés de mapping restent internes et ne sont pas envoyées à MMW ; seules les valeurs configurées, les champs d'affichage réservés et les valeurs scalaires compatibles v1 sont projetés. Les collections sont bornées à 100 éléments par réponse et les contenus archivés sont exclus. Les propriétés de présentation documentées par le modèle Android (`subtitle`, `icon`, `order`, `writable`, `options`, `media`) sont filtrées et validées ; les choix stockés comme chaînes sont normalisés en `{ "value", "label" }`.

`sites.data_revision` progresse à chaque création, modification, transition éditoriale, restauration et publication planifiée, y compris pour les brouillons. Cette révision globale peut provoquer un rafraîchissement inutile pour un acteur qui ne voit pas le brouillon modifié, mais ne laisse pas un appareil conserver une révision identique alors que son brouillon visible a changé.

Les routes POST `/v1/auth/start`, `/v1/auth/verify` et `/v1/auth/pair` suivent les formes de requête/réponse v1. `start` accepte `username`, `password`, `device_name`, et renvoie soit un défi TOTP limité dans le temps, soit un jeton appareil si le fournisseur authentifie directement. `verify` accepte `challenge_id`, `method: "totp"`, `code`; `pair` accepte `code`, `device_name`. Les réponses authentifiées contiennent `status: "authenticated"`, `device_token` et `expires_at: null`.

Le raccordement au fournisseur d'identité reste volontairement fourni par le site via l'interface `IdentityProvider`. Celui-ci doit vérifier les identifiants, limiter les tentatives, expirer et consommer une seule fois chaque défi TOTP/code d'appairage, puis retourner un acteur et un site vérifiés. Le CMS n'enregistre pas les mots de passe, secrets TOTP ou codes d'appairage. `PdoDeviceTokenIssuer` vérifie l'appartenance au site et ne persiste que le hash SHA-256 du jeton. Au démarrage actuel, aucun adaptateur n'est configuré : les routes d'authentification renvoient 503; elles ne sont donc pas prêtes pour des connexions en production.

`POST /v1/changes` applique `update_fields` sur les formulaires et `create_item`, `update_item`, `delete_item` sur les galeries configurées comme modifiables. Chaque champ reçu est ramené vers son `source_field_id` déclaré par le site, puis revalidé par `ContentService`. Si `review_before_publish` est actif, les créations et modifications sont enregistrées en revue et la réponse vaut `pending_review`; sinon elles restent des brouillons CMS jusqu'à publication. Les suppressions de galerie archivent l'entrée pour conserver son historique. Les contrôles de permission et les clés `client_request_id` sont appliqués par le cœur du CMS. `item_action` vérifie le module et l'action déclarée, puis délègue au `BusinessActionExecutor` du site, qui doit revérifier l'objet, son état, les permissions et l'idempotence côté outil métier. Sans connecteur, l'API renvoie 501. Le manifeste de découverte et l'administration de configuration restent à livrer.

Exemple de mapping interne pour une collection :

```json
{
  "id": "services",
  "kind": "records",
  "title": "Services",
  "source": {
    "type_id": "service",
    "title_field": "name",
    "subtitle_field": "short_description"
  },
  "fields": [
    { "id": "duration", "source_field_id": "duration_minutes", "type": "number", "label": "Durée" }
  ]
}
```

Pour un module `form`, `source` doit aussi contenir le `content_id` stable du contenu. Les données restent vides tant qu'aucun module n'est configuré. Les routes d'authentification nécessitent encore l'adaptateur d'identité du site; les actions métier nécessitent leurs connecteurs. Le manifeste de découverte reste à livrer. La commande CLI d'émission de jeton sert uniquement aux essais locaux et ne doit pas devenir le parcours d'authentification d'un déploiement.
