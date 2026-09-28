# Intégration avec Mon Manager Web

Le CMS Core doit alimenter ou implémenter le protocole existant.
Référence : `jasmin-abernathy/lepotager-site-manager/docs/protocol-v1.md`.

## Mapping initial

- contenu simple -> `form`
- collections -> `records`
- agenda -> `calendar`
- galerie -> `gallery` / `media_library`
- demandes -> `requests`

Avant d'ajouter un nouveau `kind`, vérifier qu'aucune primitive existante ne suffit et que le besoin est réutilisable par plusieurs intégrations.
