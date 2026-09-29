# Roadmap

## P0-A — Contrat de départ documenté

- [x] Décrire les interfaces logiques du CMS Core.
- [x] Poser le modèle initial de contenu et la concurrence optimiste.
- [x] Décrire les rôles, permissions et règles de refus par défaut.
- [x] Définir les états et transitions éditoriales initiaux.
- [x] Définir l'immutabilité des révisions et la sémantique de restauration.
- [x] Définir les garanties de persistance, transactions, isolation par site et idempotence.
- [x] Cartographier le protocole MMW v1 et relever l'écart documentaire `media_library`.

Référence : [`P0-CORE-CONTRACT.md`](P0-CORE-CONTRACT.md). Ces cases valident la spécification initiale, pas une implémentation stable.

## P0-B — Implémentation et validation locale

- [x] Choisir le runtime serveur de référence (PHP 8.5+) et le moteur initial (MariaDB via PDO).
- [ ] Valider les extensions et l'accès MariaDB réels sur l'hébergement de référence.
- [ ] Implémenter registres de modèles, services de contenu, autorisation, révisions et transactions.
- [ ] Implémenter le workflow (aperçu, revue, planification, publication atomique) et la restauration sans réécriture d'historique.
- [ ] Compléter l'adaptateur MMW sans modifier les contrats v1 non pris en charge.
- [ ] Ajouter les tests de contrat locaux listés dans `P0-CORE-CONTRACT.md`.
- [ ] Vérifier tous les accès inter-sites et mutations par des tests de refus.
- [ ] Documenter séparément la mise à jour de `media_library` dans le contrat MMW avant de la déclarer officiellement v1.

Un prototype PHP interne est présent dans `src/`, avec migrations, commandes de bootstrap, tests du domaine, une première couche HTTP privée (`GET /v1/config`, `GET /v1/snapshot`) et la projection bornée de contenus configurés via `ContentService`. Les nouveaux tests doivent encore être exécutés dans un environnement PHP réel. L'authentification standard du protocole MMW, le manifeste de découverte, l'administration de configuration et les mutations restent à construire avant tout déploiement.

## P1 — Fonctions éditoriales indispensables

- [ ] Navigation / menus.
- [ ] Blocs de contenu réutilisables (texte, image, CTA, cartes, FAQ, galerie, statistiques), déclaratifs et sans code.
- [ ] Bibliothèque média complète : recherche, filtres, texte alternatif selon le contexte, variantes, réutilisation et suppression sûre.
- [ ] SEO essentiel : métadonnées, URLs canoniques, sitemap et partage social.
- [ ] Premier adaptateur de référence.
- [ ] Site pilote générique réutilisant le CMS Core.
- [ ] Tests de contrat locaux.

## P1 — Kits métier

- [ ] Kit Artisan / TPE.
- [ ] Kit Association.
- [ ] Kit PME / services.
- [ ] Jeu de données d'exemple.
- [ ] Validation qu'un kit n'ajoute aucun code client à MMW.

## P2

- [ ] Kit Collectivité / école.
- [ ] Kit Profession libérale / santé.
- [ ] Journal d'audit (acteur, action, date, appareil).
- [ ] Import / export structurés et sauvegardes restaurables.
- [ ] Gestion portefeuille multi-sites.
- [ ] Catalogue de connecteurs.
