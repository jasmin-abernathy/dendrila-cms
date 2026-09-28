# Rôles et permissions

## Rôles de base

- `owner` : contrôle complet ;
- `admin` : contenus, médias, navigation, modules et utilisateurs autorisés ;
- `editor` : crée, modifie et publie les contenus autorisés ;
- `contributor` : crée et modifie sans publication directe par défaut ;
- `viewer` : lecture seule.

## Permissions atomiques

- `content.read`
- `content.create`
- `content.update`
- `content.delete`
- `content.publish`
- `content.restore_revision`
- `media.read`
- `media.upload`
- `media.update`
- `media.delete`
- `navigation.manage`
- `seo.manage`
- `users.read`
- `users.manage`
- `devices.read`
- `devices.revoke`
- `audit.read`

Les permissions sont revérifiées côté serveur à chaque action.

Les droits se règlent par domaine et action — par exemple contenu (`read`, `create`, `update`, `submit_for_review`, `publish`, `schedule`, `archive`, `restore_revision`), médias (`read`, `upload`, `update`, `delete`), navigation (`read`, `manage`) et membres (`read`, `manage`). Les actions métier exposées par `records` et `calendar` ont chacune leur permission distincte ; activer un module ne confère pas automatiquement toutes ses actions.
