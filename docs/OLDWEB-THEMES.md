# Créer un thème pour Dendrila Retro

Canal 57, Borne 02 et Signal 96 servent d’exemples. Le navigateur reçoit une page HTML et des feuilles
CSS. PHP prépare le rendu côté serveur et le back-office gère les réglages.
Un thème est installé avec le code du site par la personne qui le maintient.
Le catalogue de thèmes reste ouvert à d’autres contributions.

## Contrat de rendu

| Point | Règle |
| --- | --- |
| HTML | Canal 57 et Borne 02 utilisent [HTML 4.01 Strict](https://www.w3.org/TR/html401/), avec son doctype complet et déclaration UTF-8 par `meta http-equiv`. Utiliser les titres, listes, liens, formulaires et images du standard. Signal 96 utilise les pratiques HTML 3.2. |
| CSS | Canal 57 et Borne 02 utilisent [CSS niveau 2, recommandation de 1998](https://www.w3.org/TR/1998/REC-CSS2-19980512/). Utiliser les sélecteurs simples, largeurs fixes, flottants, marges, bordures et positions du standard. Signal 96 utilise CSS 1. |
| JavaScript | Zéro JavaScript côté navigateur, y compris scripts intégrés, événements HTML, URL `javascript:` et dépendances livrées par le thème. Navigation et formulaire fonctionnent avec des liens, des ancres et des requêtes HTTP. |
| Mise en page | Largeur fixe et stable sur mobile comme sur ordinateur. Les 820 px de Canal 57 sont un exemple. Le défilement horizontal est attendu sur un écran étroit, avec accès par clavier à toutes les zones. |
| Médias | Illustrations locales GIF, PNG et JPEG. Respecter le texte de remplacement et le mode de masquage des GIF. Garder les proportions et limiter les dimensions à celles du cadre. |
| Dépendances | Servir les ressources depuis le site. Éviter les polices et médias distants, les pixels de suivi et les contenus intégrés. |
| Accessibilité | Utiliser des labels, un ordre de lecture logique, des liens identifiables, un focus visible et des contrastes lisibles. Une image décorative a un attribut `alt=""`. |

Exemples à écarter pour rester dans le profil rétro : `main`, `nav`,
`section`, `video`, `canvas`, `data-*`, Flexbox, Grid, variables CSS,
coins arrondis CSS, animations CSS et requêtes de média conditionnées à la
largeur. Pour des encadrés rétro, utiliser par exemple `solid`, `double`,
`inset`, `outset` ou `ridge`. La validation vise le code HTML/CSS. Le
fonctionnement sur un navigateur datant de 2000 doit être testé séparément.

### Signal 96 et fonds personnalisés

Signal 96 reprend les usages HTML de 1996, CSS 1 et douze GIF locaux. Le motif de Kenney (Pattern Pack Pixel 1.1, tile_0039) est adapté en gris-beige clair et sous CC0 1.0. Les trois thèmes proposent dans **Fond du site** : fond du thème, couleur unie ou image répétée sur les deux axes, à taille naturelle. Le fichier GIF, PNG ou JPEG reste disponible lorsqu’on passe en mode couleur, sauf si on le retire. Le fond téléversé suit les règles de brouillon, publication et restauration des autres médias. Pour les sites Signal 96 déjà installés, appliquer la palette d’origine pour retrouver les nouvelles couleurs sans écraser les images.

## Ajouter un thème

1. Choisir un identifiant stable, en minuscules, par exemple `club-radio`.
2. Ajouter `public/assets/club-radio.css`, à partir de la
   [feuille de Canal 57](../public/assets/oldweb-fixed-portal.css).
3. Pour modifier la structure, créer `src/Oldweb/club-radio.php` à partir du
   [gabarit de Canal 57](../src/Oldweb/portal-page.php). Pour changer surtout
   le style, réutiliser `portal-page.php`.
4. Ajouter une entrée dans [`PortalThemes::AVAILABLE`](../src/Oldweb/PortalThemes.php).

```php
'club-radio' => [
    'label' => 'Club radio',
    'stylesheet' => 'assets/club-radio.css',
    'template' => 'portal-page.php',
    'defaults' => [
        'banner' => ['background' => '#102040', 'text' => '#FFFFFF', 'border' => 'double'],
    ],
],
```

La palette facultative `defaults` s’applique à la première installation
avec ce thème. Sur un site existant, le changement de thème utilise les
réglages d’apparence du brouillon, sauf si la personne applique la palette
d’origine du thème sélectionné. Le nom du thème, les couleurs, les bordures, les polices et
les images se prévisualisent et se publient ensemble.

Borne 02 et Signal 96 déclarent aussi un fichier `content` de démonstration dans
`examples/oldweb/`. Il reprend les clés du contenu générique et n’écrase
ni l’identité du site ni ses textes personnalisés. Le gabarit et ses
ressources sont inclus dans le ZIP par `bin/package-retro.py`. L’assistant,
le site installé et les exports statiques passent par `PortalRenderer`.
Le rendu de démonstration prend le contenu d’exemple et la palette initiale.
La comparaison visuelle utilise ces mêmes valeurs dans un même navigateur.
Un nom personnalisé ou une autre police peut changer le rendu.

### Traduire le thème

Déclare les exemples français dans `exemples-content.json` et leurs équivalents
anglais sous `exemples-content.en.json` dans `examples/oldweb/`. Garde les mêmes
clés dans les deux fichiers et ajoute-les à la liste des fichiers empaquetés
dans `bin/package-retro.py`. Utilise `$language` pour l’attribut `lang` et
`$tr('Libellé en français')` pour les libellés du gabarit. Ajoute leurs
traductions dans [`PortalLocale.php`](../src/Oldweb/PortalLocale.php).

### Données disponibles dans un gabarit PHP

- `$content` contient des textes simples. Les afficher avec
  `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- `$appearance` contient les couleurs, bordures et références d’images déjà
  validées. Le contrat des zones est dans [`PortalAppearance::ZONES`](../src/Oldweb/PortalAppearance.php).
- `$theme` fournit le chemin local de la feuille de style.
- `$preview` indique le brouillon, `$still` le masquage des GIF. Inclure `portal-view.php` pour réutiliser
  les fonctions de rendu des couleurs, de contrôle du GIF et de construction
  des liens privés d’images en brouillon.
- Le rendu conserve l’association entre zones et réglages de l’éditeur.
  Documenter les zones masquées si le thème choisit de ne pas toutes les
  afficher. Pour ajouter une zone éditable, modifier le contrat des zones,
  prévoir les valeurs de repli des anciens sites et adapter les tests.
- Déposer les images de contenu via l’éditeur et les éventuels éléments
  décoratifs propres au thème avec son code. Éviter l’HTML ou le CSS brut
  venant d’un formulaire. Les gabarits PHP et le registre proviennent du
  code maintenu, sans téléchargement de fichier exécutable depuis
  l’interface d’administration.

## Vérifier avant de proposer le thème

- Valider la page rendue avec le [validateur HTML du W3C](https://validator.w3.org/),
  et la feuille avec le [validateur CSS](https://jigsaw.w3.org/css-validator/)
  configuré pour CSS 2.
- Tester avec JavaScript désactivé, au clavier et dans une fenêtre étroite.
  Vérifier le défilement horizontal, les titres longs, le contraste des
  couleurs éditées et le masquage des GIF.
- Tester l’aperçu d’une image en brouillon, la publication, puis le
  rétablissement de la version précédente. Vérifier que le thème réapparaît
  avec ses réglages et ses images.
- Lancer `composer test`. Donner le nom du thème, une capture, la licence
  et l’origine des ressources utilisées dans la pull request.
