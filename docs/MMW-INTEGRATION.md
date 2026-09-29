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


## Première couche HTTP livrée

Le routeur PHP expose maintenant GET /v1/config et GET /v1/snapshot. Les deux routes exigent les en-têtes de protocole v1 et un jeton appareil résolu côté serveur vers une appartenance de site. Les contrôles HTTP ne prennent jamais site_id ou actor_id depuis un payload client. Le snapshot transmet l'acteur au `ContentService` : les droits de lecture et la visibilité des brouillons sont donc réévalués côté serveur.

La migration 002_api_devices_and_settings.sql ajoute les appareils et les réglages de configuration API. Une définition de module peut associer ses champs à un contenu ou à un type de contenu CMS avec `source` et `source_field_id`. Ces clés de mapping restent internes et ne sont pas envoyées à MMW ; seules les valeurs configurées, les champs d'affichage réservés et les valeurs scalaires compatibles v1 sont projetés. Les collections sont bornées à 100 éléments par réponse et les contenus archivés sont exclus. Les propriétés de présentation documentées par le modèle Android (`subtitle`, `icon`, `order`, `writable`, `options`, `media`) sont filtrées et validées ; les choix stockés comme chaînes sont normalisés en `{ "value", "label" }`.

`sites.data_revision` progresse à chaque création, modification, transition éditoriale, restauration et publication planifiée, y compris pour les brouillons. Cette révision globale peut provoquer un rafraîchissement inutile pour un acteur qui ne voit pas le brouillon modifié, mais ne laisse pas un appareil conserver une révision identique alors que son brouillon visible a changé.

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

Pour un module `form`, `source` doit aussi contenir le `content_id` stable du contenu. Les données restent vides tant qu'aucun module n'est configuré. Les mutations, les routes de connexion TOTP/appairage et le manifeste de découverte ne sont pas encore livrés. La commande CLI d'émission de jeton sert uniquement aux essais locaux et ne doit pas devenir le parcours d'authentification d'un déploiement.
