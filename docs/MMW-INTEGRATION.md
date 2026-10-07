# Intégration avec Mon Manager Web

Le CMS Core doit alimenter ou implémenter le protocole existant.
Référence : `jasmin-abernathy/lepotager-site-manager/docs/protocol-v1.md`.

## Mapping initial

- contenu simple -> `form`
- collections -> `records`
- agenda -> `calendar`
- galerie -> `gallery`
- bibliothèque média mobile -> `media_library` (contrat v1 formalisé dans Mon Manager Web)
- demandes -> `requests`

Le contrat MMW v1 documente désormais `media_library`, son snapshot `items`, ses champs déclaratifs et les opérations `update_item`, `rotate_left`, `rotate_right` et `delete_item` selon les capacités du module. Un ancien client reste libre d'ignorer ce `kind`; le serveur ne doit donc jamais rendre son support indispensable aux autres modules.

Le protocole v1 documente les champs `text`, `multiline`, `boolean`, `single_choice`, `number`, `url` et `email`. Les champs internes CMS comme `rich_text`, `blocks` ou `relation` doivent être projetés de façon sûre ou rester indisponibles dans le client ; ils ne deviennent pas automatiquement de nouveaux types v1.

Les mutations génériques v1 couvrent `update_fields` pour `form`, `create_item`/`update_item`/`delete_item` pour `gallery`, `item_action` déclaré pour `records`/`calendar`, et les opérations documentées de `media_library`. Le serveur revérifie droits, appartenance de l'objet, état courant et idempotence dans tous les cas.

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


## Module natif Dendrila Privacy

Dendrila Privacy utilise les primitives `settings` déjà prévues par MMW v1 : aucun nouveau `kind`, aucune route et aucun en-tête de protocole ne sont ajoutés.

Pour un acteur disposant de `privacy.read`, `GET /v1/config` ajoute le module réservé `privacy`. Le module est modifiable uniquement lorsque l'acteur possède aussi `privacy.manage`.

Les champs exposés sont volontairement plats et bornés :

- `consent_enabled` — booléen ;
- `consent_layout` — `bar` ou `card` ;
- `consent_retention_days` — entier de 30 à 365 ;
- `consent_footer_link` — booléen ;
- `scan_schedule` — `off`, `daily` ou `weekly`.

`GET /v1/snapshot` projette ces valeurs sous `data.privacy`. Une mutation `POST /v1/changes` avec le module `privacy` et l'action `update_fields` passe par les permissions serveur de Dendrila Privacy, l'idempotence commune du CMS et avance `sites.data_revision` exactement une fois.

L'identifiant de module `privacy` est réservé au noyau. Une configuration de site qui tente de déclarer manuellement un module du même identifiant est refusée afin d'éviter qu'un module stocké masque ou remplace la capacité native.


### Documentation légale Dendrila Privacy dans MMW

Le même principe s'étend au profil documentaire sans créer de nouveau `kind` MMW :

- `privacy_legal` utilise `settings` pour l'identité, les flux, les risques, les cookies, le suivi e-mail, les outils externes et l'autorité de contrôle ;
- `privacy_treatments` utilise `records` pour les traitements structurés ;
- `privacy_sources` utilise `records` pour les services et sources de collecte indirecte ;
- `privacy_documents` utilise `records` pour présenter l’état des trois documents publics et une action explicite « Valider / republier ».

Ces quatre identifiants sont réservés au noyau. Leur visibilité dépend de `privacy.read` et leur caractère modifiable de `privacy.manage`.

Les créations, modifications et suppressions utilisent `POST /v1/changes` avec les actions v1 existantes (`update_fields`, `create_item`, `update_item`, `delete_item`). Les lignes de traitement et de source reçoivent un identifiant natif stable de 32 caractères hexadécimaux. Les mutations partagent l'idempotence et la révision globale du CMS.

Le profil complet reste stocké côté serveur. MMW ne reçoit que les champs explicitement projetés par ces modules et ne reçoit jamais le journal d'audit ni les preuves techniques des scans.


### Validation des documents publics depuis MMW

Le module `privacy_documents` ne transmet pas le HTML généré. Il expose seulement, pour chaque document, son état (`À compléter`, `Prêt à valider`, `Publié` ou `À revalider`), une synthèse des points à corriger et la date de dernière validation.

L'action `item_action` / `approve` est déclarée par le serveur, demande confirmation côté client, n'est jamais rejouable hors connexion et passe par `privacy.manage`. Une validation échoue tant que le renderer signale des données indispensables manquantes. Après modification du profil ou du manifeste de consentement, l'empreinte change et MMW affiche automatiquement le document comme `À revalider`.


### Présentation guidée sans nouveau `kind`

MMW v1 accepte maintenant quelques options purement déclaratives et bornées pour améliorer les longs parcours sans embarquer de logique Dendrila Privacy dans l'application :

- `group_id`, `group_title`, `group_description`, `group_order` regroupent plusieurs modules apparentés dans l'accueil ;
- `field_sections` découpe un module `settings` en étapes nommées, chacune listant explicitement ses `field_ids` ;
- `item_label` et `empty_message` rendent les collections `records` plus compréhensibles.

Ces options ne modifient ni les permissions, ni la validation, ni la sémantique de mutation. Un ancien client les ignore et continue à afficher le formulaire à plat. Le serveur valide qu'une configuration sectionnée couvre chaque champ exactement une fois afin qu'aucun champ ne disparaisse silencieusement.

Dendrila Privacy utilise dix étapes pour `privacy_legal` : responsable et contacts ; structure ; site et hébergement ; collecte ; transferts et décisions automatisées ; situations sensibles et AIPD ; cookies ; e-mails et messageries ; services externes et sauvegardes ; autorité de contrôle. La progression affichée par MMW correspond à la **navigation dans le formulaire**, jamais à un score de conformité.
