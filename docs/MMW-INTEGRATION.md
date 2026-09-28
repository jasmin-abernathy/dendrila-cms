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
