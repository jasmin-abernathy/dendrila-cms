# Rôles et permissions

## Rôles de base

- `owner` : contrôle complet ;
- `admin` : contenus, médias, navigation, modules et utilisateurs autorisés ;
- `editor` : crée, modifie et publie les contenus autorisés ;
- `contributor` : crée, lit et modifie ses propres brouillons, puis les soumet à revue ; il ne voit pas les brouillons des autres et ne publie pas directement ;
- `viewer` : lecture seule.

## Permissions atomiques

- `content.read`
- `content.read_draft` (tous les brouillons) et `content.read_own_draft` (ses brouillons)
- `content.create`
- `content.update` (tous les contenus) et `content.update_own` (ses contenus)
- `content.delete`
- `content.publish`
- `content.publish_without_review`
- `content.submit_for_review`, `content.schedule`, `content.archive`, `content.unpublish`, `content.preview`
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

Les permissions initiales sont dans `database/seeders/default-roles.json`. La publication sans revue est un droit séparé (`content.publish_without_review`) accordé seulement à `owner` et `admin` par défaut ; la politique `review_before_publish` demeure prioritaire.
