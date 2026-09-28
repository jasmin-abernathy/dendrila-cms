# Mon Manager Web CMS

Socle CMS modulaire destiné à compléter **Mon Manager Web (MMW)**.

Ce dépôt ne remplace pas l'application `jasmin-abernathy/lepotager-site-manager`.
Il fournit le **noyau serveur générique** qui permet à plusieurs sites différents
d'exposer à MMW des contenus, médias, rôles, révisions, workflows éditoriaux et modules
sans recréer un back-office spécifique pour chaque client.

## Objectif V1

La V1 est considérée exploitable lorsque :

- un nouveau site peut être branché sans modification du code de l'application MMW ;
- les contenus structurés, médias, menus, brouillons et publications sont gérés ;
- les modifications sont versionnées et restaurables ;
- les rôles et permissions sont configurables ;
- les primitives restent compatibles avec le protocole MMW ;
- au moins deux sites différents utilisent le même noyau ;
- un kit métier peut être instancié sans forker le moteur.

## Priorités

1. CMS Core générique.
2. Modèles de contenu.
3. Rôles et permissions.
4. Brouillons / publication / dépublication.
5. Révisions et restauration.
6. Navigation.
7. Bibliothèque média et SEO essentiel.
8. Kits Artisan/TPE puis Association.

Voir `docs/ROADMAP.md`.

## Principes

- Pas de code spécifique client dans MMW.
- Pas de téléchargement de code exécutable dans l'application.
- Le site public reste libre de sa technologie.
- Le serveur reste l'autorité sur les données et permissions.
- Toute nouvelle primitive doit être générique et réutilisable.
- Les outils tiers passent par des connecteurs côté serveur.
- Export et réversibilité doivent rester possibles.
- Accessibilité et sobriété sont des contraintes de base, pas des options.
