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
