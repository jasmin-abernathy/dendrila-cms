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

Le protocole v1 documente les champs `text`, `multiline`, `boolean`, `single_choice`, `number`, `url` et `email`. Les champs internes CMS comme `rich_text`, `blocks` ou `relation` doivent être projetés de façon sûre ou rester indisponibles dans le client ; ils ne deviennent pas automatiquement de nouveaux types v1.

Les mutations génériques v1 sont limitées à `update_fields` pour `form`, `create_item`/`update_item`/`delete_item` pour `gallery`, et `item_action` déclaré pour `records`/`calendar`. Le serveur revérifie droits, état de l'objet et idempotence dans tous les cas.

Avant d'ajouter un nouveau `kind`, vérifier qu'aucune primitive existante ne suffit et que le besoin est réutilisable par plusieurs intégrations. Le contrat P0 complet et les limites actuelles figurent dans [`P0-CORE-CONTRACT.md`](P0-CORE-CONTRACT.md).
