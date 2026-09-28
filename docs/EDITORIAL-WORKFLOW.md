# Workflow éditorial

```text
draft -> review -> published -> archived
  |         |          |
  +-------->+----------+
```

## Règles

- Une modification d'un contenu publié crée une nouvelle révision de travail.
- La version publique précédente reste servie tant que la nouvelle n'est pas publiée.
- La publication directe dépend de `content.publish`.
- Une dépublication ne détruit pas le contenu.
- Restaurer une révision crée une nouvelle révision ; l'historique n'est pas réécrit.
