# Styles visuels

Les styles visuels sont indépendants des kits fonctionnels.

Un kit répond à « de quelles pages et fonctions le site a-t-il besoin ? ». Un style répond à « quel résultat graphique veut-on obtenir ? ».

## Règle de composition

```text
kit fonctionnel + style visuel + identité de marque + modules activés = site final
```

## Galerie initiale

- `image-forward` — **Centré sur les images** : grandes images, peu de texte simultané, respiration forte.
- `quiet-elegant` — **Sobre et élégant** : espaces généreux, typographie soignée, détails discrets.
- `simple-illustrated` — **Simple avec quelques illustrations** : structure claire, illustrations ponctuelles.
- `ultra-minimal` — **Très minimal** : presque aucun décor, priorité au contenu.
- `editorial` — **Éditorial** : lecture longue, titres affirmés, images intercalées.
- `graphic-rhythm` — **Dynamique et graphique** : contrastes de rythme, aplats et formes.

## Principes

Les presets utilisent des tokens sémantiques. Ils ne fixent ni métier, ni logo, ni contenu, ni police propriétaire. L’adaptateur de front traduit ces tokens vers ses composants HTML/CSS.

Les contraintes d’accessibilité sont obligatoires : contraste au moins AA, focus visible, prise en charge de `prefers-reduced-motion` et aucun média en lecture automatique.

La galerie de choix doit présenter le résultat visuel, jamais des catégories comme « business », « administratif », « artisan » ou « association ».

## Prototype local

Une galerie sans dépendance est disponible dans `examples/design-gallery/index.html`.

Pour compiler un preset en variables CSS :

```sh
php bin/compile-design-style.php quiet-elegant
```

Le compilateur n'accepte qu'un vocabulaire fermé de tokens. Il ne permet pas à une configuration de site d'injecter du CSS arbitraire.


## Design kits exécutables

Les presets de ce dépôt décrivent l'intention graphique. Les composants HTML/CSS réellement réutilisables vivent dans `jasmin-abernathy/dendrila-design-kits`.

Le raccord est local et versionné : le catalogue du dépôt de thèmes associe un `design_style_id` à un manifeste et un entrypoint installés. Le CMS vérifie que tous les tokens déclarés comme requis par le thème sont effectivement produits par `CssTokenCompiler`.

## Thèmes réellement livrés avec l’installation native

Le site public, l’aperçu d’une page et les six aperçus d’installation utilisent désormais `Runtime/Presentation`, qui passe par `PresentationResolver` et le catalogue livré dans `public/design-kits/`. La feuille CSS du thème sélectionné remplace la feuille générique `assets/site.css` pour ces parcours. Cette ancienne feuille reste disponible pour les intégrations historiques. Les formes et motifs sont des SVG locaux du thème ; aucun téléchargement de code n’a lieu pendant une requête.

| Style CMS | Thème livré |
| --- | --- |
| simple-illustrated | simple-illustrated |
| image-forward | image-centered |
| quiet-elegant | quiet-elegant |
| ultra-minimal | very-minimal |
| graphic-rhythm | dynamic-graphic |
| editorial | editorial |

Éditorial reprend la version inspirée de la grammaire de l’advarP (PR design-kits #9), avec les corrections de correspondance apportées par la PR #8. Le rendu générique des blocs reste le contrat commun ; les métadonnées d’articles et mises en page spécifiques d’un média ne sont pas inventées à partir d’une simple page. Aucun design de site client n’est remplacé automatiquement par cette intégration.

### Mettre à jour le lot de thèmes

Depuis un checkout local de la version relue de `dendrila-design-kits`, exécuter pendant le développement :

```sh
python3 bin/sync-design-kits.py ../dendrila-design-kits --revision <SHA-complet-relu>
composer test
```

Le générateur copie uniquement le catalogue, les manifestes, CSS et SVG nécessaires. `public/design-kits/bundle-lock.json` conserve le dépôt d’origine, sa révision et le SHA-256 de chaque fichier. Les tests comparent les fichiers au verrou, vérifient les références CSS locales, puis les tests navigateur comparent le thème affiché par l’aperçu à celui du site installé à 1280 et 390 pixels. Les previews autonomes des design kits ne sont pas copiées : l’assistant utilise le véritable renderer du CMS avec un contenu de référence identique.

### Identité de marque

La configuration privée ou les variables serveur peuvent définir `DENDRILA_BRAND_JSON`, par exemple :

```json
{"primary":"#204060","primary_strong":"#162c42","body_font":"neutral-sans","logo_url":"https://votre-site.example/assets/logo.svg"}
```

La validation utilise `SiteCompositionDefinition` : couleurs hexadécimales, choix de polices système et logo HTTPS. Les valeurs sont appliquées après le CSS du thème, dans son périmètre. Aucun CSS libre ni téléchargement de police n’est accepté. Une palette personnalisée doit être vérifiée pour son contraste. Les noms de variables existants `DENDRILA_THEME` et les six identifiants de style restent compatibles. Il n’existe pas encore de panneau visuel de personnalisation de ces réglages.
