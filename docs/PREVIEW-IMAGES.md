# Photographies des aperçus d'installation Dendrila

Les six thèmes de l'assistant utilisent **les mêmes quatre photos locales** au format WebP, uniquement pour l'aperçu « Exemple complet ». Elles ne sont **jamais copiées dans la médiathèque du site installé**, ni téléchargées depuis un serveur tiers au moment de consulter un aperçu.

Les fichiers sont des photographies diffusées en **CC0 1.0 (domaine public)** sur Wikimedia Commons, vérifiées le 30 octobre 2026 :

| Fichier local | Œuvre et source Wikimedia Commons | Auteur mentionné sur Commons | Licence |
| --- | --- | --- | --- |
| `public/assets/install-demo/mountains.webp` | [Mountains Landscape.jpg](https://commons.wikimedia.org/wiki/File:Mountains_Landscape.jpg) | Auteur non indiqué sur la fiche (source Pikwizard) | CC0 1.0 |
| `public/assets/install-demo/forest.webp` | [Landscape of a wild forest.jpg](https://commons.wikimedia.org/wiki/File:Landscape_of_a_wild_forest.jpg) | SpessiG | CC0 1.0 |
| `public/assets/install-demo/architecture.webp` | [Modern architecture build.jpg](https://commons.wikimedia.org/wiki/File:Modern_architecture_build.jpg) | Auteur non indiqué sur la fiche (source Pikwizard) | CC0 1.0 |
| `public/assets/install-demo/flowers.webp` | [Flowers, images.jpg](https://commons.wikimedia.org/wiki/File:Flowers,_images.jpg) | Renukarenu1861 | CC0 1.0 |

Licence : https://creativecommons.org/publicdomain/zero/1.0/deed.fr

Ces fichiers sont des dérivés techniques redimensionnés/compressés de ces originaux CC0. La mention de source est conservée par transparence même si CC0 n'impose pas l'attribution.

## Règles de construction

- `bin/prepare-install-preview-images.py` récupère ces quatre sources officielles, contrôle le type et le poids, puis génère des WebP redimensionnés (1200px maximum, qualité 76).
- Les fichiers WebP générés **sont commités dans la branche** pour que l'installation distribuée fonctionne **hors connexion**, sans requête externe ou suivi des visiteurs.
- `bin/build-web-release.py` les inclut déjà dans l'archive puisque le répertoire `public/` entier y figure.
- Le contrôle de l'archive exige la présence des quatre fichiers.
- La galerie est un **aperçu fictif**, indépendant de l'activation des modules ; seule la page vierge est créée lors de l'installation réelle.
