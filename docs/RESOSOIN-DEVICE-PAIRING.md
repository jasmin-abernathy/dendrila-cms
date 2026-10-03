# Appairage RésoSoin avec Dendrila

Ce fournisseur d’identité autorise uniquement l’appairage d’un appareil déjà préautorisé. Il ne crée pas de compte et ne gère ni mot de passe ni TOTP.

## Initialiser le site de démonstration

Le kit sert à tester la synchronisation éditoriale du profil fictif. Il ne stocke aucune donnée de patient, réservation ou santé.

1. Après déploiement du code CMS, appliquer les migrations :

   `php bin/migrate.php`

2. Créer un site isolé avec les collections du kit cabinet :

   `php bin/bootstrap-site.php resosoin-demo "Cabinet des Jardins · démo" demo-owner true cabinet-medical`

   Cette commande configure les types éditoriaux, les modules API Services et Diplômes et formations, et le rôle propriétaire. Elle utilise `DB_DSN`, `DB_USERNAME` et `DB_PASSWORD`.

3. Émettre un code temporaire depuis le serveur :

   `php bin/issue-device-pairing-code.php resosoin-demo demo-owner 600`

   La commande affiche une seule fois un code aléatoire valable 10 minutes. Le CMS ne stocke que son SHA-256.

4. Dans **RésoSoin → Gérer le site public du cabinet**, saisir l’origine HTTPS du CMS et ce code. Le code est consommé une fois ; l’appareil reçoit un jeton stocké dans le stockage sécurisé Android.

Avant d’exposer `/v1/auth/pair`, configurer une limite de débit au niveau du serveur HTTP ou du pare-feu. Ne jamais exposer la commande d’émission par HTTP.

## Synchronisation éditoriale

L’espace soignant peut créer les contenus publics suivants :

- services (nom, description, durée, lien de réservation) ;
- diplômes et formations (diplôme, DU/DIU ou formation, établissement, année, précisions).

Après l’écriture, l’app relit le snapshot Dendrila et affiche le statut réel. Selon la politique du site, le nouvel élément est un brouillon ou attend une validation.

Cette étape n’ajoute pas encore l’action de publication ni le rendu public dynamique des contenus. Un brouillon ou un élément en revue ne doit pas être présenté comme publié sur le site.

## Base de données

Dendrila exige actuellement PHP 8.5 avec PDO MySQL et ses migrations utilisent des fonctions et du DDL MySQL. SQLite n’est donc pas utilisable sans un adaptateur et des migrations dédiés. Le kit de démonstration ne justifie pas, à lui seul, ce chantier de portage.
