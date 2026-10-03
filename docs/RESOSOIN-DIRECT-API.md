# Préparer le branchement direct de RésoSoin à Dendrila

## Décision

L’APK RésoSoin soignant sera le client mobile du CMS Dendrila. Elle se connectera directement à l’API Dendrila du site du cabinet. Aucune application Mon Manager Web distincte n’est requise dans ce parcours.

Le contrat API déjà décrit par le protocole compatible v1 sert uniquement de format d’échange : découverte/configuration, authentification par appareil, lecture du snapshot et mutations déclarées. L’APK ne télécharge jamais de code ou d’interface exécutable.

## Site de démonstration

Le kit `kits/cabinet-medical/kit.json` amorce les pages publiques du Cabinet des Jardins · démo et le profil fictif du Dr Camille Martin. Aucune donnée clinique ou donnée patient ne fait partie de ce site.

## Parcours cible dans l’APK

1. Depuis **Gérer le site public du cabinet**, saisir ou scanner l’adresse HTTPS du site.
2. Vérifier l’origine et découvrir la configuration Dendrila de ce site.
3. Appairer cet appareil avec un code à usage unique généré côté site.
4. Stocker le jeton d’appareil dans le stockage sécurisé Android ; ne jamais persister le mot de passe ou le code d’appairage.
5. Charger la configuration et le snapshot ; afficher l’état et l’heure de dernière synchronisation.
6. Prévisualiser les changements avant publication ; transmettre seulement les champs publics déclarés par le CMS.
7. Rafraîchir le snapshot après une écriture et traiter les révisions concurrentes sans écraser les modifications serveur.

Les pages publiques, les présentations de praticiens, les informations pratiques et le référencement appartiennent au CMS. Les réservations, agendas de soin, documents et données de santé restent dans leurs services dédiés.

## Préconditions techniques identifiées

Le CMS expose déjà les routes privées de configuration, de snapshot, d’appairage et de mutations prévues par l’API. Cependant, les routes d’authentification renvoient actuellement `503` tant qu’un fournisseur d’identité propre au site n’est pas injecté. Le kit médical n’active pas cette authentification et ne crée aucun compte de test.

Avant un essai connecté, il reste à livrer dans Dendrila :

- le fournisseur d’identité et l’émission/révocation des jetons par appareil ;
- la configuration des modules et leur projection vers les pages du cabinet ;
- un accès public sûr au contenu publié pour le rendu du site ;
- l’environnement de démonstration HTTPS avec données exclusivement fictives.

Puis dans l’APK RésoSoin :

- l’écran de connexion/appairage au CMS ;
- le client API direct avec validation HTTPS et refus des redirections hors origine ;
- la persistance Android sécurisée du jeton ;
- le chargement, l’état de synchronisation, la prévisualisation et la gestion des conflits ;
- des tests du parcours connecté contre un CMS de démonstration, sans lancer de déploiement.

Tant que ces préconditions ne sont pas réunies, l’interface doit afficher « non connecté » ou « synchronisation indisponible » et ne jamais laisser croire qu’une modification est publiée.
