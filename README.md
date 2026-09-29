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

Le contrat P0 de départ, ses interfaces logiques et les limites de compatibilité MMW v1 sont décrits dans `docs/P0-CORE-CONTRACT.md`.

Le socle de référence est PHP 8.5+ / PDO / MariaDB, documenté dans `docs/ARCHITECTURE.md`. L'implémentation cœur commence dans `src/`; elle ne remplace pas les échanges d'authentification du protocole MMW.

## Prototype local du CMS Core et première API

Prérequis : PHP 8.5+ avec `pdo_mysql` et `mbstring`, Composer et une base MariaDB dédiée.

```sh
composer install
composer lint
composer test
php bin/migrate.php
php bin/bootstrap-site.php <site_id> <nom_du_site> <actor_id_proprietaire> [review_before_publish=true|false]
```

Les commandes de migration et de création de site attendent `DB_DSN`, `DB_USERNAME` et `DB_PASSWORD` dans l'environnement. Le fichier `.env.example` montre la forme attendue, mais le projet ne charge volontairement pas `.env` automatiquement. Les fichiers média doivent rester hors du webroot.

La première couche HTTP expose GET /v1/config et GET /v1/snapshot. Elle exige X-Lepotager-Protocol: 1 et un jeton d'appareil valide dans X-Lepotager-Device-Token. Les jetons de développement peuvent être émis avec php bin/issue-device-token.php <site_id> <actor_id> <device_name> après la migration 002. Cette commande ne remplace pas encore les routes de connexion TOTP et d'appairage prévues par le protocole : ne pas l'utiliser comme parcours de production.

À ce stade, les réglages d'API d'un site commencent avec une liste de modules vide ; le snapshot renvoie donc des données vides avec une révision. La configuration des modules et leur projection depuis les contenus CMS restent à construire. Le manifeste de découverte n'est pas encore publié, car les parcours d'authentification annoncés dans ce manifeste ne sont pas encore implémentés.

## Principes

- Pas de code spécifique client dans MMW.
- Pas de téléchargement de code exécutable dans l'application.
- Le site public reste libre de sa technologie.
- Le serveur reste l'autorité sur les données et permissions.
- Toute nouvelle primitive doit être générique et réutilisable.
- Les outils tiers passent par des connecteurs côté serveur.
- Export et réversibilité doivent rester possibles.
- Accessibilité et sobriété sont des contraintes de base, pas des options.
