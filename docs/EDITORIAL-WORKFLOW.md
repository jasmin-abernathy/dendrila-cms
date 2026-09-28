# Workflow éditorial

```text
draft -> review -> scheduled -> published -> archived
  |         |          |
  +-------->+----------+
```

## Règles

- Une modification d'un contenu publié crée une nouvelle révision de travail.
- La version publique précédente reste servie tant que la nouvelle n'est pas publiée.
- La publication directe dépend de `content.publish`.
- L'aperçu est privé et ne change ni l'état ni la révision publiée.
- La programmation exige `content.schedule` et `content.publish`, une date UTC, puis une revalidation des droits, de la politique et du contenu à l'échéance.
- Une planification peut être annulée vers `draft` ou `review` ; la tâche d'échéance est idempotente.
- Une dépublication ne détruit pas le contenu.
- Restaurer une révision crée une nouvelle révision ; l'historique n'est pas réécrit.
