# Appairage RésoSoin avec Dendrila

Ce premier fournisseur d’identité autorise uniquement l’appairage d’un appareil déjà préautorisé. Il ne crée pas de compte, ne gère pas de mot de passe ni de TOTP, et n’ajoute pas d’écran d’administration web.

## Mise en service sur un serveur de test

1. Déployer le code Dendrila et appliquer les migrations :

   \`php bin/migrate.php\`

   La commande utilise \`DB_DSN\`, \`DB_USERNAME\` et \`DB_PASSWORD\` déjà requis par le CMS. La migration \`003_device_pairing_codes.sql\` crée le stockage des codes.
2. Vérifier qu’un site et son membre existent déjà dans \`sites\` et \`memberships\`. Utiliser exactement leurs \`site_id\` et \`actor_id\`.
3. Dans un terminal SSH du serveur, depuis la racine du dépôt déployé, lancer :

   \`php bin/issue-device-pairing-code.php <site_id> <actor_id> 600\`

   La durée est en secondes, entre 60 et 900 ; la valeur par défaut est 600. La commande affiche un code aléatoire de 12 chiffres une seule fois. Saisir ce code immédiatement dans **RésoSoin → Gérer le site public du cabinet**, avec l’origine HTTPS du CMS.
4. Si le code a expiré, relancer la commande. Un code déjà consommé ne peut pas être réutilisé.

Ne jamais rendre cette commande accessible par HTTP, inscrire le code dans les journaux, l’envoyer par courrier électronique ou le placer dans un lien public. Le CMS ne stocke que le SHA-256 du code ; l’appareil reçoit ensuite son propre jeton aléatoire, conservé dans le stockage sécurisé Android.

## Limites actuelles

- Le fournisseur est limité à l’endpoint \`/v1/auth/pair\`. Les routes mot de passe/TOTP échouent volontairement.
- La génération du code se fait par SSH ; l’écran d’administration Dendrila permettant de le générer n’est pas encore construit.
- Avant d’exposer un site publiquement, configurer aussi une limitation de débit pour \`POST /v1/auth/pair\` au niveau du serveur HTTP / pare-feu. Le code à usage unique et son expiration ne remplacent pas cette barrière.
- La PR RésoSoin lit la configuration et le snapshot, mais n’édite ni ne publie encore les pages du cabinet.
