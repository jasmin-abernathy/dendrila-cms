# Dendrila — sites de départ issus des références

## Fonctionnement

Le choix d'un des six thèmes prépare une véritable arborescence de pages et de sections éditables dans Dendrila. Ce ne sont pas des copies HTML. L'accueil est publié ; les autres pages sont des brouillons, avec entrées de menu masquées jusqu'à relecture. Le menu et les aperçus « Pages de départ » reprennent la même arborescence que l'installation réelle.

Les galeries sont créées comme sections éditables et attendent les images de l'utilisateur. Pour les thèmes illustrés, sobres et photographiques, le module de médiathèque est présélectionné, mais décochable. Les photos des démonstrations sont des WebP locaux, non copiés dans les pages installées.

Les blocs génériques Étapes et Tarifs sont disponibles dans le back-office et le moteur de rendu. Le bloc Tarifs n'exécute aucun paiement. Une réservation, un paiement, un espace privé, une recherche SIRET ou un forum requièrent toujours leur propre module vérifié.

## Sections préparées

| Thème | Référence et source | Pages |
|---|---|---|
| Simple illustré | Matercéleste, identité neutralisée | Accueil, services, à propos, informations, galerie, FAQ, contact |
| Sobre et élégant | Démonstration de maison d'hôtes (pas de client identifié) | Accueil, présentation, espaces, informations, à découvrir, contact |
| Centré sur les images | Oscura / Vues Imprenables | Accueil, galerie, projets, actualités, à propos, contact |
| Très minimal | Prestadmin, structure de site de services | Accueil, services, méthode, à propos, offres, ressources, contact |
| Dynamique et graphique | Démonstration d'événement collectif (pas de client identifié) | Accueil, agenda, activités, à propos, partenaires, infos pratiques, contact |
| Éditorial | advarP, structure éditoriale neutralisée | Accueil, publications, dossiers, thèmes, rencontres, à propos, contact |

Les sections des sites pilotes sont ramenées à des blocs réutilisables sans imposer leurs marques, leurs tarifs, leurs données personnelles ni leurs opinions à une installation publique. Pas de blog automatique sur un thème non éditorial.

## Ce qui reste spécifique aux sites clients

Ce travail ne constitue pas une migration de contenu. Prestadmin doit encore importer les contenus et tarifs réels, ses workflows de facturation, sa carte et son outil SIRET ; Oscura doit importer les vrais albums et ateliers, puis activer le forum ; Matercéleste devra intégrer les réservations et paiements en modules natifs ; advarP nécessite l'import de ses articles et sources. Il ne faut pas annoncer ces services comme actifs tant qu'ils ne le sont pas.

## Tests ciblés

php tests/service-blocks-run.php

php tests/starter-sites-run.php

php tests/themes-run.php

php tests/install-run.php

php tests/front-run.php

Pour le back-office avec dépendances de test : charger tests/Support/InMemoryCmsStore.php avant tests/backoffice-run.php.

Les tests n'installent aucun site réel et n'envoient aucune requête métier.

## Responsive et navigation clavier

Les six thèmes Dendrila possèdent une navigation mobile native avec commande « Menu » (élément HTML details/summary sans JavaScript). Sur les tablettes et petits écrans, les compositions se restructurent dans leur kit CSS respectif : couvertures illustrées, composition photo, catalogue minimal, affiche graphique, page éditoriale. Le panneau de navigation offre des liens de 44 px minimum, des contours de focus visibles et une hauteur limitée pour les écrans en paysage. Les fichiers publics sont alignés sur le dépôt dendrila-design-kits (voir docs/RESPONSIVE.md dans ce dépôt).

Tests : php tests/themes-run.php, php tests/starter-sites-run.php, bash tests/run.sh dans Design Kits. Les tests automatiques valident le HTML et les règles CSS, mais ne remplacent pas une recette visuelle avec navigateur sur téléphone et tablette.

## Composition de l'accueil et photographies libres

Les premières sections de chaque page d'accueil sont rendues dans un conteneur \`d-home-composition\` dont la grille est définie **par le thème**. Les blocs restent indépendants et éditables dans leur ordre de lecture. La galerie photo peut précéder les cartes, être placée à côté du texte ou dominer la page ; le catalogue minimal affiche une colonne de services et un déroulement en vis-à-vis ; l'éditorial présente une « une » et un encart. Les pages intérieures restent en lecture ordinaire.

Les couvertures avec photos d'exemple utilisent exclusivement des WebP locaux Commons, en CC0 ou domaine public (sources dans \`docs/PREVIEW-IMAGES.md\`). Dans l'aperçu de l'installateur, les galeries sont illustrées sans aucune écriture en base : seul le propriétaire ajoute ses propres médias, puis personnalise ou remplace les photos du thème.

## Pied de page commun et fonds photographiques (1er novembre 2026)

Dans les six thèmes, la navigation secondaire du pied de page est un vrai élément nav, avec une grille de trois colonnes dans le CSS du kit. Sur les téléphones de moins de 25 rem, les liens occupent deux colonnes pour éviter des cibles trop étroites. Les liens ne sont pas soulignés mais conservent un focus visible au clavier. Le moteur ne limite plus le footer à six entrées : la page Contact ne disparaît plus lorsque le site compte sept pages ou davantage.

La composition « Centré sur les images » associe aux trois cartes « Projets », « Actualités » et « À propos » les fichiers Wikimedia Commons locaux architecture.webp, nature.webp et forest.webp, sous CC0 ou domaine public. Le CSS ajoute un dégradé sombre derrière le texte blanc. Ces fonds sont décoratifs, sans téléchargement distant, et ne constituent pas des médias clients enregistrés dans la base.

Voir docs/PREVIEW-IMAGES.md pour la traçabilité des droits.

## Menu burger lisible (correctif du 1er novembre 2026)

Sur les six thèmes, les commandes Menu des téléphones utilisent maintenant des fonds opaques et des couleurs explicitement contrastées. Le panneau ouvert, l'état survol/focus et les trois traits du burger reçoivent eux aussi des couleurs dédiées, non héritées d'une éventuelle transparence du header. Les tests de Design Kits contrôlent les rapports de contraste du texte normal à au moins 4,5:1 ; toutes les combinaisons testées dépassent 8:1. Les règles CSS sont incluses dans les six kits compilés et testées par le script themes-run.php.
