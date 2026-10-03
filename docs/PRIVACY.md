# Dendrila Privacy dans Dendrila CMS

Dendrila Privacy est une **capacité native du noyau Dendrila CMS**. Elle n'est pas chargée comme un plugin WordPress et ne dépend d'aucune API WordPress.

Le plugin `jasmin-abernathy/dendrila-privacy` reste la déclinaison WordPress autonome. Les deux produits partagent les mêmes principes, mais le CMS porte désormais lui-même les primitives génériques afin qu'un site Dendrila ne doive rien installer pour disposer du socle vie privée.

## Inclus dans le noyau

Chaque site possède dès sa création :

- un état local de configuration vie privée ;
- un profil documentaire destiné à préparer les informations légales sans publication silencieuse ;
- une liste bornée de pratiques externes que le site seul ne peut pas observer ;
- un moteur d'inspection sans dépendance à un constructeur de pages ;
- un historique de scans et de constats isolé par `site_id` ;
- un manifeste de consentement minimal destiné à l'adaptateur front ;
- des permissions serveur `privacy.read`, `privacy.manage` et `privacy.scan` ;
- des événements dans le journal d'audit commun du CMS.

La configuration initiale est prudente : le consentement natif est désactivé, l'analyse planifiée est désactivée et aucune conclusion juridique n'est fabriquée.

## Collecte et détection

`PrivacyInspector` reçoit des documents déjà collectés par l'adaptateur du site (HTML rendu, configuration ou autres représentations textuelles pertinentes). Le noyau **ne lance pas lui-même de requêtes HTTP vers le site ou des services tiers**.

Le premier catalogue natif reconnaît notamment Google Analytics, Google Tag Manager, Meta Pixel, Microsoft Clarity, Hotjar, Matomo, YouTube, Vimeo, Google Maps et reCAPTCHA. Les résultats distinguent :

- détection technique ;
- besoin de vérification humaine ;
- catégorie fonctionnelle ;
- caractère facultatif pour le moteur de consentement.

Le traitement de reCAPTCHA et d'outils comme Matomo reste volontairement contextuel : une signature technique ne suffit pas à conclure à elle seule au régime juridique réel.

## Consentement

`PrivacyService::consentManifest()` fournit uniquement ce dont un adaptateur front a besoin :

- activation ;
- présentation `bar` ou `card` ;
- durée de mémorisation ;
- présence du lien « gérer mes choix » ;
- services facultatifs détectés ;
- empreinte stable de configuration.

Le profil légal, les preuves de scan et le journal d'audit ne sont jamais inclus dans ce manifeste public.

Le noyau fournit maintenant `ConsentFrontend`, `ConsentRenderer`, `ConsentResourceRenderer` et les assets `public/assets/privacy-consent.*`. Ils permettent à un front Dendrila d'afficher une barre ou un encart accessible et de différer scripts, iframes et feuilles de style avant choix, sans dépendre de WordPress ni d'un constructeur visuel. Voir `PRIVACY-FRONTEND.md`.

## Profil documentaire légal natif

`LegalProfile` porte maintenant dans le noyau la structure générique auparavant concentrée dans le plugin WordPress : identité du responsable, contact vie privée/DPO, hébergeur, collecte directe ou indirecte, transferts, décisions automatisées, catégories particulières, mineurs, cookies, suivi e-mail et outils externes.

Les traitements sont structurés et bornés (8 maximum par profil) avec finalité, catégories de données, base légale, destinataires, durée de conservation, caractère obligatoire et conséquences. Les sources indirectes sont également structurées (20 maximum) et reçoivent des identifiants stables afin de pouvoir être modifiées sans ambiguïté par les futurs clients d'administration.

Le noyau applique une validation stricte et refuse les champs inconnus. Les réponses `unknown` restent possibles lorsque l'information n'est pas encore déterminée : Dendrila Privacy ne transforme pas une absence d'information en conclusion juridique.

`PrivacyService::legalProgress()` reprend la logique contextuelle du parcours WordPress : par exemple une destination de transfert n'est exigée que si un transfert est déclaré, et les détails d'un DPO ne deviennent nécessaires que lorsqu'un DPO est déclaré comme désigné. Ces états servent à guider l'interface ; ils ne constituent pas un score de conformité.

## Permissions initiales

- `owner` et `admin` : lecture, configuration et lancement/enregistrement des analyses ;
- `editor` : lecture et analyses, sans modification de la configuration globale ;
- `contributor` et `viewer` : aucun accès aux données internes de vie privée par défaut.

Toutes les décisions restent vérifiées côté serveur.

## Compatibilité MMW v1

Cette intégration n'ajoute aucune route, aucun en-tête et aucun `kind` au protocole MMW v1. Une interface cliente peut ultérieurement projeter une partie des réglages via les primitives génériques existantes, mais la capacité Privacy n'impose pas une mise à jour du protocole.

## Frontières avec le plugin WordPress

Le code du plugin WordPress n'est pas copié dans le noyau : ses hooks, options, écrans d'administration, shortcodes et intégrations Divi/Elementor restent propres à WordPress. Les fonctionnalités génériques doivent progressivement vivre dans le noyau ou dans des composants portables, puis être adaptées au CMS concerné.

Restent à raccorder côté Dendrila :

- collecteur concret raccordé au premier moteur de rendu public (le contrat `PrivacyDocumentCollector` et `PrivacyScanRunner` est prêt) ;
- interface cliente complète du profil documentaire (le modèle, la validation, la progression et les mutations serveur sont présents) ;
- connecteurs externes facultatifs (par exemple registre d'entreprise) ;
- import/migration des données depuis une installation WordPress utilisant Dendrila Privacy.

Dendrila Privacy aide à documenter et à contrôler le comportement technique d'un site ; ce n'est pas une certification juridique.
