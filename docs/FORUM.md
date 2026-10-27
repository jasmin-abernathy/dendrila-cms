# Forum Dendrila : discussions et photos

Module générique pour Vues Imprenables et les futurs sites Dendrila. La migration
007 contient les tables multitenant, le seeder ajoute des permissions atomiques,
et le service PHP ne change pas le protocole MMW v1.

## Fonctionnalités livrées

- Sujets et réponses créés en attente de modération, avec approbation ou masquage.
- Verrouillage de sujet ; lectures publiques limitées aux publications approuvées.
- Jusqu'à 4 images par message, JPEG/PNG/WebP, 6 Mio chacune ; photo transformée
  en WebP 2048 px maximum, EXIF supprimé, stockage hors webroot.
- Texte brut systématiquement échappé par ForumHtml.
- Quotas par auteur : 3 sujets par 15 min, 8 messages par 5 min ; audit modération.
- Isolation des sites dans toutes les requêtes et droits revérifiés à chaque mutation.

## Installation et raccordement du site

1. Lancer php bin/migrate.php sur MariaDB avec une sauvegarde préalable.
2. Préparer un répertoire privé accessible à PHP, en dehors du document root,
   et installer GD avec prise en charge WebP.
3. Instancier ForumPhotoStorage, ForumService et ForumHtml dans l'adaptateur web.
   ForumService attend PDO et le service AuthorizationService CMS.
4. Transformer les connexions vérifiées du site en ActorContext. Ne JAMAIS
   utiliser un actor_id proposé par un formulaire ou un en-tête non authentifié.
5. Vérifier la session, les jetons CSRF, les plafonds HTTP et les uploads avant
   d'appeler le service. Servir publicPhoto avec Content-Type image/webp,
   X-Content-Type-Options nosniff, et sans chemin de fichier public.
6. Créer les pages de listing, de sujet, de rédaction et de modération en
   utilisant les méthodes du service et les fragments ForumHtml.

Rôles : owner/admin peuvent publier et modérer ; editor/contributor publier ;
viewer et visiteurs anonymes ne peuvent pas publier. Les membres publics du
forum devront obtenir un rôle et une appartenance à leur site après inscription.

## Appels PHP disponibles

- openTopic(actor,title,body), reply(actor,topicId,body), attachPhoto(actor,messageId,bytes,alt)
- visibleTopics(siteId), visibleTopic(siteId,topicId), visibleMessages(siteId,topicId),
  publicPhoto(siteId,photoId) retourne les octets WebP si le post est public.
- moderationQueue(moderator), moderateTopic(moderator,topicId,publish),
  moderateMessage(moderator,messageId,publish), lockTopic(moderator,topicId,locked)
- ForumHtml::topics et ForumHtml::messages fournissent des fragments HTML sûrs.

## Ce qui reste avant activation sur vuesimprenables.com

- Authentification et inscription du public, sessions, formulaires, CSRF,
  interface de modération et routes HTTP du site pilote.
- Tester le SQL sur MariaDB, l'envoi de fichiers et GD/WebP sur PHP 8.5,
  la concurrence, la modération, les droits et les quotas de stockage.
- Définir une durée de conservation pour les messages/photos refusés, ainsi
  que des procédures de retrait, signalement et suppression de compte.
- Ne pas fusionner implicitement les PR médiathèque ouvertes (#24 et #25).
  Le stockage privé du forum est autonome pour éviter une dépendance instable.

Le noyau Dendrila ne fournit pas encore d'authentification de membre public.
Ne PAS exposer un formulaire anonyme ou déclarer le forum opérationnel avant
le raccordement de cette identité et les contrôles de sécurité.
