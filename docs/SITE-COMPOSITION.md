# Composition d’un site

La configuration d’un site est construite avec quatre couches indépendantes :

1. **Kit fonctionnel** — structure de départ et modules utiles.
2. **Style visuel** — résultat graphique recherché.
3. **Identité de marque** — logo, couleurs et éléments propres au client.
4. **Modules activés** — fonctions réellement disponibles sur ce site.

```text
Association + Sobre et élégant + identité du client + actualités/agenda/documents = site final
```

Le même kit peut utiliser n’importe quel style. Inversement, un même style peut être partagé par tous les kits.
Le contrat JSON expérimental est défini dans `schemas/site-composition.schema.json`.


## Résolution vers un design kit installé

Les fichiers de thème vivent dans le dépôt séparé `dendrila-design-kits`. Un déploiement installe ce dépôt à côté du front et fournit à Dendrila CMS le chemin local de son `catalog.json`.

Le CMS ne télécharge jamais un thème depuis GitHub pendant une requête. `DesignKitCatalog` résout le `design_style` vers un manifeste local, vérifie sa version, son entrypoint, ses capacités média, ses contraintes d'accessibilité et les tokens Dendrila requis.

`PresentationResolver` combine ensuite :

```text
preset design-styles/<id>/style.json
+ design kit local
+ brand_overrides
+ modules activés
= descripteur de présentation + variables CSS sûres
```

Les couleurs de marque sont limitées à des hexadécimaux complets, les URLs de logo/wordmark à HTTPS, et les polices à des piles prédéfinies. Aucun CSS arbitraire ne vient de la configuration du site.

Test local :

```sh
php bin/resolve-site-presentation.php examples/site-composition.json /chemin/vers/dendrila-design-kits/catalog.json
```

## Mise en forme des services et contact à la carte

La variante facultative layout_variant=service-showcase permet un bandeau texte-image, des encarts de mise en avant et des bénéfices sans identité client figée. Le thème et le parcours guidé sont deux choix indépendants.

Le mode contact_entry est none par défaut. Les autres modes sont direct, form et guided ; guided exige le module guided_assistant et une URL de parcours existant. L'affichage peut être button, link, card, banner ou floating. L'interface appelle ce choix « Parcours guidé » et autorise un libellé propre à chaque site. Aucun Jardinier n'est imposé aux autres installations, et aucune base ou intelligence artificielle n'est requise par ce simple point d'entrée.
