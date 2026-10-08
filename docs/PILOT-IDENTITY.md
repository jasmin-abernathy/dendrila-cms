# Connexion d’un site pilote existant

## Principe

Dendrila CMS ne crée pas un second compte propriétaire lorsqu’un site possède déjà une identité et le protocole historique MMW. Le premier pont fourni est `legacy_mmw_pairing`, destiné à une migration contrôlée.

Ce pont réutilise trois tables déjà présentes sur le site :

- `site_admins` comme source de l’acteur et de son rôle ;
- `mmw_pair_codes` pour les codes d’association courts, expirables et à usage unique ;
- `mmw_devices` pour les jetons appareil, leur dernier accès et leur révocation.

Les mots de passe ne sont jamais lus par Dendrila dans ce mode. `auth/start` et `auth/verify` restent refusés. La méthode publique annoncée par le manifeste doit donc être `pairing_code`.

Après consommation d’un code valide, le rôle legacy est traduit par une table de correspondance explicite vers un rôle Dendrila. La membership Dendrila est créée ou mise à jour, puis le jeton est écrit dans `mmw_devices`. Une révocation effectuée par le back-office existant coupe ainsi immédiatement l’accès Dendrila.

## Vues Imprenables — pilote retenu

Vues Imprenables possède déjà le bon socle : comptes réels, mot de passe Argon2id, second facteur e-mail pour le back-office, codes d’association MMW de 8 chiffres valables 10 minutes et appareils révocables. Son manifeste annonce déjà uniquement `pairing_code`.

Le pilote Dendrila doit donc utiliser ce flux :

1. se connecter au back-office Vues Imprenables avec le parcours existant ;
2. créer un code d’association à usage unique ;
3. saisir ce code dans la médiathèque Dendrila ;
4. Dendrila consomme le code, synchronise le rôle autorisé et écrit le nouvel appareil dans `mmw_devices` ;
5. le même back-office Vues Imprenables permet ensuite de révoquer l’appareil.

Aucun mot de passe ni code e-mail n’est copié vers Dendrila.

## Configuration privée

Sur le serveur uniquement :

```text
DENDRILA_IDENTITY_PROVIDER=legacy_mmw_pairing
DENDRILA_IDENTITY_SITE_ID=vues-imprenables
DENDRILA_IDENTITY_HMAC_SECRET=<même secret HMAC que le site existant>
DENDRILA_IDENTITY_ROLE_MAP={"owner":"owner"}
```

Le secret HMAC est nécessaire pour vérifier les hashes déjà utilisés par `mmw_pair_codes` et `mmw_devices`. Il ne doit jamais être copié dans Git, un document de relais ou un journal.

Le rôle cible doit déjà exister dans Dendrila. La migration `009_auth_rate_limits.sql` ajoute une limite persistante aux échecs d’appairage. Le pont impose également une temporisation sur les échecs afin de réduire les essais automatisés.

## Préflight

Après migrations, exécuter :

```bash
php bin/identity-preflight.php
```

Le script ne révèle aucun secret. Il vérifie les tables nécessaires, affiche les acteurs legacy et leur correspondance de rôle, puis indique si le site Dendrila doit encore être initialisé.

Si le site n’existe pas encore, utiliser l’un des `actor_id` mappés affichés :

```bash
php bin/bootstrap-site.php vues-imprenables "Vues Imprenables — Oscura" ACTOR_ID false
```

Relancer ensuite le préflight.

## Interface web

La médiathèque lit `/.well-known/lepotager-site-manager.json` avant de présenter les formulaires de connexion. Elle n’affiche que les méthodes annoncées par `auth_methods`. Pour Vues Imprenables, seul le panneau « Code d’association » est donc visible.

Le manifeste reste public et ne reçoit jamais le jeton appareil. Les appels privés continuent d’envoyer le jeton uniquement dans `X-Lepotager-Device-Token`.

## Limites de ce pont

Ce pont sert à la migration du pilote ; il n’est pas un nouveau format d’identité universel. Il suppose que les tables legacy et Dendrila sont accessibles par la même connexion PDO. Il n’ajoute pas TOTP au protocole de Vues Imprenables et ne remplace pas son second facteur web par e-mail.

Le bridage des échecs d’association est volontairement global au site, car le contrat `IdentityProvider` ne reçoit pas l’adresse réseau du client. Le seuil protège contre le brute-force mais peut temporairement bloquer de nouveaux appairages après trop d’échecs. Une évolution du contrat d’identité pourra introduire un contexte client explicite si un besoin multi-sites le justifie.

## Point de validation humaine

Le code peut être validé en CI sans secret réel. La première connexion réelle exige ensuite l’hébergement : migrations MariaDB, site Dendrila initialisé avec l’acteur existant, variables privées ci-dessus, stockage média privé et routage vers l’API Dendrila.

Ne remplacer ni le site public Vues Imprenables ni son API MMW historique pendant cette validation. Le pilote Dendrila peut être monté sous un chemin privé séparé jusqu’à validation de la médiathèque et de l’identité.
