# Révisions

Chaque modification significative crée une révision immuable.

Champs minimum :
- `revision_id`
- `entity_id`
- `entity_type`
- `version`
- `author_id`
- `created_at`
- `change_summary`
- `snapshot`
- `source` (`web`, `mmw`, `import`, `system`)

Une restauration charge l'ancien snapshot puis crée une nouvelle révision avec ce contenu.
