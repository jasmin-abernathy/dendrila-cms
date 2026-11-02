# Import éditorial Prestadmin57 vers Dendrila — test, novembre 2026

## Installation existante préservée

L'installateur de Dendrila a été achevé par le propriétaire sur
`https://prestadmin-test.lepotager.org` avec son compte administrateur
et son fichier SQLite privé. Une URL de production
(`https://www.prestadmin57.fr`) a été saisie par erreur lors de la
création du compte : **seul `APP_BASE_URL` du fichier de configuration
privé a été corrigé**, pour utiliser l'URL du site de test. Une copie
privée de la configuration d'origine est conservée hors du web.
Aucun compte, mot de passe ou secret TOTP n'a été modifié.

## Source et contenus

Importer uniquement les pages **publiques et éditoriales** depuis les
fichiers HTML existants de `prestadmin57.fr`, sans copier le back-office
ou les données clients. Le script
`php bin/import-prestadmin-content.php --dry-run` prépare et valide les
pages sans modifier la base. `--apply` réalise une sauvegarde SQLite
privée, puis utilise les services natifs ContentService et NavigationService
pour créer les révisions et publier les pages.

| Statut | Page native | Source |
|---|---|---|
| Publiée | Accueil | `index.html` |
| Publiée | Services / Prestations | `index.html`, offres |
| Publiée | Méthode | `index.html`, bénéfices et accompagnement |
| Publiée | À propos | `index.html`, présentation |
| Publiée | Offres | `index.html`, prestations et tarifs annoncés |
| Publiée | Outils / Ressources | Liens publics vers les services réels |
| Publiée | Contact | Coordonnées publiques et bloc formulaire Dendrila |
| Publiée | Facturation électronique | `facturation-electronique/index.html` |
| Publiée | Carte | `carte/index.html` |
| Brouillon | Mentions légales d'origine | `mentions-legales.html` |
| Brouillon | Confidentialité d'origine | `confidentialite.html` |

Les contenus sont des blocs Dendrila éditables : textes, cartes,
valeurs clés, étapes, tarifs, boutons et formulaire. Les intitulés
et prix repris proviennent du site réel au moment de l'import. Les
deux documents juridiques ne sont pas publiés automatiquement, car la
politique de l'ancien site doit être revue pour les traitements de
la version Dendrila (notamment le formulaire de contact).

Le thème `ultra-minimal` choisi lors de l'installation est conservé.
La palette Prestadmin est enregistrée dans la personnalisation native :
bordeaux `#9d2941`, fonds clairs, texte foncé et logo d'origine.
Le logo et l'image du bandeau sont regroupés dans
`public/assets/prestadmin/`; cette dernière n'est **pas encore**
intégrée comme média éditable, le module média n'ayant pas été
activé lors de l'installation.

## Fonctionnalités spécialisées à ne pas usurper

La version Dendrila **ne remplace pas encore** les fonctionnalités
`/projet/`, `/outils/siret/`, `/paiement/`,
`/facturation-electronique/` (formulaire de commande et dépôt privé),
ni `/espace-prestadmin/`. Les liens vers ces outils conduisent
explicitement à la version existante `www.prestadmin57.fr` quand
l'outil natif équivalent n'existe pas.

Le formulaire natif de contact reste désactivé tant qu'une adresse
de destination valide et le transport mail du serveur n'ont pas
été configurés et testés. Le bouton e-mail classique vers
`contact@prestadmin57.fr` est conservé.

## Précautions

- Aucune écriture dans le répertoire de production Prestadmin57.
- Aucune fusion de PR ni bascule DNS.
- Le site de test demeure **noindex, nofollow, noarchive**.
- Sauvegarde SQLite privée réalisée avant l'import, avec accès 0600.
- Le script refuse un hôte autre que `prestadmin-test.lepotager.org` et
  une base autre que SQLite, et ne doit pas écraser les pages déjà
  éditées indépendamment par le propriétaire.
- Les identifiants et la configuration privée sont hors Git.

## Rééquilibrage natif de l'accueil (novembre 2026)

L'ancienne maquette importée ressemblait à une liste en deux colonnes plutôt
qu'au véritable Prestadmin. Correction dans le **kit de base Très minimal**
du dépôt `dendrila-design-kits` : bandeau avec deux colonnes et image
configurable, grille de quatre prestations, section ressources et données
administratives, bénéfices, présentation avec image, CTA pleine largeur et
responsive. La mise en page s'appuie sur les blocs Dendrila, sans CSS spécifique
au domaine Prestadmin ni copie de l'ancienne page HTML.

Prestadmin-test utilise désormais :
- `hero_image_url` dans les réglages natifs d'apparence, pointant vers son
  image de bandeau locale d'origine ;
- les blocs `cards` pour facturation électronique/RGPD et prestations ;
- `key_values` pour les bénéfices ;
- `image_text` pour la présentation illustrée, avec la photo Pexels déjà
  employée dans le vrai site, recopiée dans les actifs du test ;
- `guided_assistant` pour « Assistant Prestadmin », avec trois choix
  orientant vers les vraies pages de services, facturation ou contact ;
- deux boutons natifs dans le bandeau, dont « Être guidé dans ma demande ».

L'assistant natif est un parcours de premier niveau sans transmission ni IA
imposée. Il ne remplace pas encore le parcours complet « Le Jardinier »
conditionnel du Potager. Les contenus Prestadmin restent des révisions dans
sa base SQLite et peuvent être modifiés dans le back-office. Aucun changement
n'a été apporté à la production ni aux secrets du propriétaire.

Le kit comprend aussi une photo Pexels localement servie en WebP pour les
**futures** installations « Très minimal ». Le site Prestadmin-test conserve
ses propres actifs de marque, sans injecter son logo ou ses couleurs comme
valeurs obligatoires pour les autres propriétaires de Dendrila.
