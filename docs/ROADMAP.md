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

## Situation de la version installable (30 octobre 2026)

- **Disponible pour tests :** ZIP d'installation, diagnostic des prérequis, vérification de propriété sans SSH, six thèmes, sélection médiathèque/forum, connexion à une base MariaDB vide, propriétaire/TOTP, administration des pages, contenus, médias et menus, brouillons et publication.
- **À terminer avant d'annoncer une version générique prête à installer partout :** stabiliser toute la CI, puis installer depuis le ZIP sur un domaine pilote HTTPS avec MariaDB réelle ; vérifier l'installateur, les comptes, les mises à jour, sauvegardes/restaurations, les permissions et l'affichage mobile.
- **À tester séparément avant migration d'un client existant :** conservation de son design, de ses identifiants et de ses contenus, mise à jour GitHub autonome, adaptateurs métiers spécifiques et restauration du site antérieur.
- **Intégrations encore incomplètes :** appairage réel Mon Manager Web, installation de Dendrila Bookings comme module, récupération d'accès/codes de secours, forum avec publication de photographies par l'interface utilisateur.
- **Diffusion :** la PR #29 est encore ouverte ; aucune bascule de domaine client ne doit résulter de sa fusion ou de l'installation d'essai.

## P0-B — Implémentation et validation locale

- [x] Choisir le runtime serveur de référence (PHP 8.5+) et le moteur initial (MariaDB via PDO).
- [ ] Valider les extensions et l'accès MariaDB réels sur l'hébergement de référence.
- [ ] Implémenter registres de modèles, services de contenu, autorisation, révisions et transactions.
- [ ] Implémenter le workflow (aperçu, revue, planification, publication atomique) et la restauration sans réécriture d'historique.
- [ ] Compléter l'adaptateur MMW sans modifier les contrats v1 non pris en charge.
- [ ] Ajouter les tests de contrat locaux listés dans `P0-CORE-CONTRACT.md`.
- [ ] Vérifier tous les accès inter-sites et mutations par des tests de refus.
- [x] Documenter `media_library` dans le contrat MMW v1 et aligner le CMS sur ce contrat.

**État vérifié au 30 octobre 2026 :** le noyau comporte désormais un installateur graphique (six thèmes, modules, MariaDB, compte propriétaire TOTP), un ZIP de distribution sans Composer sur l'hébergement, un back-office natif, la médiathèque, le forum modéré, la gestion des pages, des blocs, des brouillons et des menus. Les tests PHP/PDO et la restauration de bases passent dans la CI ; le test navigateur du dernier commit doit encore être confirmé. Il ne s'agit pas d'une validation d'une installation générique sur deux sites clients réels. L'association MMW v1 dépend encore de l'adaptateur d'identité, et les actions métier comme `item_action` exigent leurs connecteurs dédiés. Les kits fonctionnels et le catalogue d'extensions serveur ne constituent pas encore un parcours public d'installation.

## P1 — Fonctions éditoriales indispensables

- [x] Navigation / menus : stockage versionné, permissions, source publique et tests PDO.
- [x] Blocs de contenu réutilisables (texte, image, CTA, cartes, FAQ, galerie, statistiques), déclaratifs et sans code.
- [x] Bibliothèque média complète : recherche et filtres serveur, interface web accessible, texte alternatif contextuel, variantes, réutilisation, concurrence optimiste et suppression sûre.
- [x] SEO essentiel : métadonnées versionnées, URLs canoniques, sitemap et partage social Open Graph.
- [x] Premier adaptateur de référence (`PublicPageRenderer`) pour un front HTML/CSS léger.
- [x] Site pilote générique réutilisant le CMS Core, générable localement depuis un design kit installé.
- [ ] Tests de contrat locaux.

## P1 — Vie privée native

- [x] Intégrer le socle Dendrila Privacy au CMS : stockage local, permissions, inspection générique, constats et manifeste de consentement.
- [x] Initialiser automatiquement les réglages Privacy pour chaque nouveau site et migrer les sites existants.
- [x] Définir le contrat de collecte et le runner `collecte → inspection → enregistrement` pour les adaptateurs front.
- [x] Raccorder un collecteur concret au premier moteur de rendu public Dendrila.
- [x] Fournir la barre / l'encart de consentement et le blocage déclaratif avant choix, indépendants du moteur de templates.
- [x] Exposer les réglages Privacy de base dans MMW via le module natif `settings` sans étendre le protocole v1.
- [x] Porter le modèle natif du profil documentaire : identité, traitements, flux, risques, cookies, e-mail et outils externes, avec validation et progression contextuelle.
- [x] Exposer dans MMW les champs du profil documentaire, les traitements et les sources indirectes via les primitives `settings` et `records` existantes.
- [x] Ajouter les aides contextuelles côté contrat MMW et exposer l’état/validation des documents publics dans le client d’administration.
- [x] Structurer le parcours documentaire pour mobile avec regroupement Privacy, dix étapes courtes, aides contextuelles et sauvegarde section par section dans MMW.
- [x] Importer les réglages, le profil documentaire et le dernier scan depuis le plugin WordPress Dendrila Privacy, avec rejeu idempotent.
- [x] Générer les mentions légales, l'information vie privée et la politique cookies depuis le profil natif, avec aperçu, validation humaine et invalidation automatique après modification.
- [x] Raccorder ces documents aux routes publiques du premier adaptateur front Dendrila (`/mentions-legales`, `/confidentialite`, `/cookies`) avec refus des versions obsolètes.

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
- [x] Ajouter un adaptateur de front complet qui applique aussi les variantes de structure (hero, galeries, alignement et illustrations).
- [ ] Tester chaque style sur mobile, grand écran, navigation clavier et préférences de mouvement réduit.

## P2

- [ ] Kit Collectivité / école.
- [ ] Kit Profession libérale / santé.
- [ ] Journal d'audit (acteur, action, date, appareil).
- [ ] Import / export structurés et sauvegardes restaurables.
- [ ] Gestion portefeuille multi-sites.
- [ ] Catalogue de connecteurs.
