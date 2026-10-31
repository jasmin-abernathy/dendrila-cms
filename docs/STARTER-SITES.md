# Dendrila — sites de départ issus des références

## Fonctionnement

Le choix d'un des six thèmes prépare une véritable arborescence de pages et de sections éditables dans Dendrila. Ce ne sont pas des copies HTML. L'accueil est publié ; les autres pages sont des brouillons, avec entrées de menu masquées jusqu'à relecture. Le menu et les aperçus « Pages de départ » reprennent la même arborescence que l'installation réelle.

Les galeries sont créées comme sections éditables et attendent les images de l'utilisateur. Pour les thèmes illustrés, sobres et photographiques, le module de médiathèque est présélectionné, mais décochable. Les photos des démonstrations sont des WebP locaux, non copiés dans les pages installées.

Les blocs génériques Étapes et Tarifs sont disponibles dans le back-office et le moteur de rendu. Le bloc Tarifs n'exécute aucun paiement. Une réservation, un paiement, un espace privé, une recherche SIRET ou un forum requièrent toujours leur propre module vérifié.

## Sections préparées

| Thème | Référence et source | Pages |
|---|---|---|
| Simple illustré | Matercéleste, identité neutralisée | Accueil, prestations, approche, rendez-vous, galerie, FAQ, contact |
| Sobre et élégant | Démonstration de maison d'hôtes (pas de client identifié) | Accueil, maison, chambres, séjour, environs, contact |
| Centré sur les images | Oscura / Vues Imprenables | Accueil, galeries, séries, ateliers, sténopé, démarche, contact |
| Très minimal | Prestadmin, structure de site de services | Accueil, prestations, méthode, interlocuteur, facturation, outils, contact |
| Dynamique et graphique | Démonstration d'événement collectif (pas de client identifié) | Accueil, programme, ateliers, collectif, partenaires, infos pratiques, contact |
| Éditorial | advarP, structure éditoriale neutralisée | Accueil, analyses, dossiers, rubriques, entretiens, à propos, contact |

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
