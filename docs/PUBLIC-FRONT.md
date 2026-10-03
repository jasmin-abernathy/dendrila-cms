# Adaptateur de front public

`PublicPageRenderer` est le premier adaptateur HTML/CSS de référence de Dendrila CMS.

Il prend un contenu déjà validé par le CMS, le descripteur produit par `PresentationResolver`, les URLs publiques des médias et la navigation, puis produit une page autonome et sémantique.

## Garanties

- aucun HTML arbitraire provenant du contenu n'est exécuté ;
- les URLs d'assets sont HTTPS ou relatives au site ;
- les liens publics sont HTTPS, relatifs, ancres ou `mailto:` valide ;
- les points focaux et formes média sont appliqués sans modifier le fichier source ;
- les embeds externes passent par `ConsentResourceRenderer` et restent bloqués avant consentement ;
- les tokens structurels du preset deviennent des attributs `data-dendrila-*` consommés par le design kit ;
- le renderer n'a pas besoin d'accéder à GitHub ni de télécharger du code à l'exécution.

## Dendrila Privacy

`FrontPageDocumentCollector` fournit le collecteur concret attendu par `PrivacyScanRunner`. Il demande au `PublicPageSource` les pages à inspecter, les rend avec le même renderer public, puis transmet ce HTML à `PrivacyInspector`.

Ainsi, le scan observe le HTML que le site Dendrila produirait réellement, y compris les services externes différés avant consentement.

## Jeu de référence

`examples/design-reference-page.json` contient un contenu générique commun destiné aux tests et aux futures comparaisons de thèmes. Il couvre texte, image, cartes, chiffres-clés, paires clé/valeur, galerie, FAQ, embed externe et CTA.


## Documents vie privée publics

`PublicPrivacyDocumentRouter` raccorde les documents approuvés par Dendrila Privacy au premier front de référence sans dupliquer leur contenu dans des pages CMS ordinaires.

Routes natives stables :

- `/mentions-legales` → mentions légales ;
- `/confidentialite` → politique de confidentialité ;
- `/cookies` → politique cookies et traceurs.

`PrivacyServicePublicDocumentSource` fournit uniquement une version approuvée dont l’empreinte correspond encore aux données sources. Une route inconnue ou un document devenu obsolète renvoie donc `null` au routeur du site, qui peut répondre en 404 plutôt que publier une version périmée. Le fragment HTML généré est borné et contrôlé avant insertion dans la coque du design kit ; scripts, styles, iframes, objets et documents HTML complets y sont refusés.


## Hiérarchie des sections

Les blocs de collection peuvent fournir des intertitres optionnels via `heading`. `PublicPageRenderer` produit alors `.d-section__heading > .d-heading` et une classe stable `.d-section--block-<type>` sur la section.

Ces hooks permettent aux design kits d'espacer et différencier cartes, statistiques, galeries, FAQ et embeds sans embarquer de logique métier dans le thème.

Un CTA autonome n'affiche son libellé qu'une seule fois : le bouton constitue l'action et le texte visible.
