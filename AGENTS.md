# Règles de travail

## GitHub Actions

- Économie maximale de GitHub Actions.
- Regrouper les modifications avant push.
- Ne pas déclencher de build lourd pour de la documentation ou des schémas.
- Utiliser `[skip ci]` lorsque le changement a déjà été vérifié localement et qu'aucun runner n'apporte de valeur.
- Les tests de contrat et validations de schémas doivent pouvoir tourner localement.

## Compatibilité

Avant toute modification technique :
- lire `jasmin-abernathy/repo-factory/COMPATIBILITY-AND-DEPRECATION-PLAYBOOK.md` ;
- lire `jasmin-abernathy/lepotager-site-manager/docs/protocol-v1.md` ;
- ne jamais casser le contrat MMW existant sans version majeure explicite.

## Architecture

- `mon-manager-web-cms` contient le noyau CMS serveur.
- `lepotager-site-manager` reste l'application cliente MMW.
- Ne pas réintroduire de logique BMH ou client spécifique dans le noyau.
- Un connecteur tiers traduit vers les primitives génériques.
- Les permissions sont validées côté serveur à chaque mutation.
- Toute mutation concernée par le protocole doit rester idempotente.
