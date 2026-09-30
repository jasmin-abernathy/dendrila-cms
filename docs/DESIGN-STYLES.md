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
