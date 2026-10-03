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

Un prototype PHP interne est présent dans `src/`, avec migrations, commandes de bootstrap, tests du domaine, une couche HTTP privée (`GET /v1/config`, `GET /v1/snapshot`, `POST /v1/changes`) et la projection bornée de contenus configurés via `ContentService`. Les routes d'authentification MMW v1 exigent encore un adaptateur `IdentityProvider` spécifique au site. Les mutations des formulaires et galeries sont raccordées aux contenus du CMS ; `item_action` attend les connecteurs métier propres à chaque site. Le manifeste de découverte, l'administration de configuration et la couche média restent à construire avant tout déploiement.

## P1 — Fonctions éditoriales indispensables

- [ ] Navigation / menus.
- [ ] Blocs de contenu réutilisables (texte, image, CTA, cartes, FAQ, galerie, statistiques), déclaratifs et sans code.
- [ ] Bibliothèque média complète : recherche, filtres, texte alternatif selon le contexte, variantes, réutilisation et suppression sûre.
- [ ] SEO essentiel : métadonnées, URLs canoniques, sitemap et partage social.
- [ ] Premier adaptateur de référence.
- [ ] Site pilote générique réutilisant le CMS Core.
- [ ] Tests de contrat locaux.

## P1 — Vie privée native

- [x] Intégrer le socle Dendrila Privacy au CMS : stockage local, permissions, inspection générique, constats et manifeste de consentement.
- [x] Initialiser automatiquement les réglages Privacy pour chaque nouveau site et migrer les sites existants.
- [x] Définir le contrat de collecte et le runner `collecte → inspection → enregistrement` pour les adaptateurs front.
- [ ] Raccorder un collecteur concret au premier moteur de rendu public Dendrila.
- [x] Fournir la barre / l'encart de consentement et le blocage déclaratif avant choix, indépendants du moteur de templates.
- [x] Exposer les réglages Privacy de base dans MMW via le module natif `settings` sans étendre le protocole v1.
- [x] Porter le modèle natif du profil documentaire : identité, traitements, flux, risques, cookies, e-mail et outils externes, avec validation et progression contextuelle.
- [x] Exposer dans MMW les champs du profil documentaire, les traitements et les sources indirectes via les primitives `settings` et `records` existantes.
- [ ] Ajouter les aides contextuelles et l'ergonomie guidée du parcours documentaire dans le client d'administration.
- [x] Importer les réglages, le profil documentaire et le dernier scan depuis le plugin WordPress Dendrila Privacy, avec rejeu idempotent.
- [x] Générer les mentions légales, l'information vie privée et la politique cookies depuis le profil natif, avec aperçu, validation humaine et invalidation automatique après modification.
- [ ] Raccorder ces documents aux routes publiques du premier adaptateur front Dendrila.

Voir [`PRIVACY.md`](PRIVACY.md) et [`PRIVACY-FRONTEND.md`](PRIVACY-FRONTEND.md).

## P1 — Kits fonctionnels

- [ ] Kit Artisan / TPE.
- [ ] Kit Association.
- [ ] Kit PME / services.
- [ ] Jeu de données d'exemple.
- [ ] Validation qu'un kit n'ajoute aucun code client à MMW.

## P1 — Styles visuels

- [x] Séparer contractuellement les kits fonctionnels et les styles visuels.
- [x] Ajouter le schéma `design-style` et les premiers presets orientés résultat graphique.
- [x] Ajouter un schéma de composition site = kit + style + marque + modules.
- [x] Ajouter une galerie HTML autonome utilisant le même contenu pour les six styles ; les miniatures finales restent à produire.
- [x] Ajouter un compilateur de référence qui traduit les tokens autorisés en variables CSS bornées.
- [x] Raccorder les presets à un catalogue local versionné de `dendrila-design-kits`, sans téléchargement de code à l'exécution.
- [x] Ajouter le contrat média non destructif (forme + point focal) utilisé par les design kits.
- [ ] Ajouter un adaptateur de front complet qui applique aussi les variantes de structure (hero, galeries, alignement et illustrations).
- [ ] Tester chaque style sur mobile, grand écran, navigation clavier et préférences de mouvement réduit.

## P2

- [ ] Kit Collectivité / école.
- [ ] Kit Profession libérale / santé.
- [ ] Journal d'audit (acteur, action, date, appareil).
- [ ] Import / export structurés et sauvegardes restaurables.
- [ ] Gestion portefeuille multi-sites.
- [ ] Catalogue de connecteurs.
