# Dendrila CMS

Noyau CMS modulaire, pilotable par **Mon Manager Web (MMW)** et réutilisable par plusieurs sites.

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
- un kit fonctionnel peut être instancié sans forker le moteur ;
- un style visuel peut être changé sans modifier le kit fonctionnel.

## Priorités

1. CMS Core générique.
2. Modèles de contenu.
3. Rôles et permissions.
4. Brouillons / publication / dépublication.
5. Révisions et restauration.
6. Navigation.
7. Bibliothèque média et SEO essentiel.
8. Vie privée et consentement natifs (Dendrila Privacy).
9. Kits fonctionnels Artisan/TPE puis Association.
10. Galerie de styles visuels indépendante des kits métier.

Voir `docs/ROADMAP.md`.

La composition visuelle est volontairement séparée du métier : `docs/DESIGN-STYLES.md` décrit les presets graphiques et `docs/SITE-COMPOSITION.md` la combinaison kit fonctionnel + style visuel + identité de marque.

**Dendrila Privacy fait partie du socle du CMS.** Chaque site dispose nativement d'un état local de vie privée, d'un moteur générique d'inspection de services/traceurs, d'un profil documentaire et d'un manifeste de consentement. Le noyau fournit aussi un rendu accessible de consentement et des ressources différées avant choix pour les fronts Dendrila. Aucun plugin WordPress n'est nécessaire pour un site Dendrila ; le plugin `dendrila-privacy` reste la déclinaison WordPress autonome. Voir `docs/PRIVACY.md` et `docs/PRIVACY-FRONTEND.md`.

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
php bin/compile-design-style.php quiet-elegant
```

Les commandes de migration et de création de site attendent `DB_DSN`, `DB_USERNAME` et `DB_PASSWORD` dans l'environnement. Le fichier `.env.example` montre la forme attendue, mais le projet ne charge volontairement pas `.env` automatiquement. Les fichiers média doivent rester hors du webroot.

La couche HTTP expose GET /v1/config et GET /v1/snapshot, POST /v1/changes, ainsi que les routes POST /v1/auth/start, /v1/auth/verify et /v1/auth/pair du contrat MMW v1. Toutes exigent X-Lepotager-Protocol: 1. Les routes privées exigent aussi un jeton appareil dans X-Lepotager-Device-Token. Les mutations `update_fields` et les opérations `create_item`, `update_item`, `delete_item` de galerie sont raccordées aux contenus et aux permissions du CMS ; `item_action` nécessite un connecteur métier propre au site. Les routes d'authentification sont présentes, mais renvoient 503 tant qu'un adaptateur `IdentityProvider` propre au site n'est pas injecté. Le CMS ne crée pas un second système de mots de passe : l'adaptateur doit valider les identifiants, TOTP et codes d'appairage auprès du fournisseur d'identité du site. `PdoDeviceTokenIssuer` enregistre ensuite le jeton sous forme de hash après vérification de l'appartenance au site. Les jetons de développement peuvent être émis avec php bin/issue-device-token.php <site_id> <actor_id> <device_name> après la migration 002 ; cette commande ne constitue pas un parcours de production.

La galerie statique de démonstration des six directions visuelles se trouve dans `examples/design-gallery/`. Elle utilise le même contenu pour éviter de confondre style graphique et secteur métier. Le compilateur CLI transforme un preset `design-styles/*/style.json` en variables CSS bornées : aucun CSS arbitraire n'est accepté depuis la configuration.

Par défaut, les réglages d'API d'un site commencent avec une liste de modules vide. Une fois un mapping de module renseigné en base, le snapshot projette les contenus par l'intermédiaire des services et permissions du CMS ; il ne renvoie que les champs explicitement déclarés. La configuration des modules n'a pas encore d'écran d'administration, et le manifeste de découverte n'est pas publié.

## Principes

- Pas de code spécifique client dans MMW.
- Pas de téléchargement de code exécutable dans l'application.
- Le site public reste libre de sa technologie.
- Le serveur reste l'autorité sur les données et permissions.
- Toute nouvelle primitive doit être générique et réutilisable.
- Les outils tiers passent par des connecteurs côté serveur.
- Les kits fonctionnels et les styles visuels restent indépendants.
- La vie privée est un socle du CMS, pas une extension à installer après coup.
- Export et réversibilité doivent rester possibles.
- Accessibilité et sobriété sont des contraintes de base, pas des options.
