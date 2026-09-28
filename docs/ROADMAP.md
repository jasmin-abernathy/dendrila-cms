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

- [ ] Choisir le runtime serveur de référence et le moteur de persistance initial.
- [ ] Implémenter registres de modèles, services de contenu, autorisation, révisions et transactions.
- [ ] Implémenter le workflow, publication atomique et restauration sans réécriture d'historique.
- [ ] Implémenter l'adaptateur MMW sans modifier les contrats v1 non pris en charge.
- [ ] Ajouter les tests de contrat locaux listés dans `P0-CORE-CONTRACT.md`.
- [ ] Vérifier tous les accès inter-sites et mutations par des tests de refus.
- [ ] Documenter séparément la mise à jour de `media_library` dans le contrat MMW avant de la déclarer officiellement v1.

## P1 — Fonctions éditoriales indispensables

- [ ] Navigation / menus.
- [ ] Bibliothèque média complète.
- [ ] SEO essentiel.
- [ ] Journal d'audit.
- [ ] Import / export structurés.
- [ ] Premier adaptateur de référence.
- [ ] Tests de contrat locaux.

## P1 — Kits métier

- [ ] Kit Artisan / TPE.
- [ ] Kit Association.
- [ ] Jeu de données d'exemple.
- [ ] Validation qu'un kit n'ajoute aucun code client à MMW.

## P2

- [ ] Kit PME / services.
- [ ] Kit Collectivité / école.
- [ ] Kit Profession libérale / santé.
- [ ] Gestion portefeuille multi-sites.
- [ ] Catalogue de connecteurs.
