# SEO natif

Dendrila CMS fournit un premier socle SEO générique, séparé du contenu éditorial et réutilisable par tous les sites.

## Métadonnées par contenu

Les métadonnées sont stockées dans `seo_metadata`, avec concurrence optimiste et rattachement au contenu par `(site_id, content_id)`.

Champs disponibles :

- titre SEO ;
- description ;
- URL canonique ;
- titre et description de partage ;
- image de partage ;
- indicateur d’indexation.

Les URLs canoniques et images sociales acceptent soit une URL HTTPS absolue, soit un chemin racine. Un chemin racine n’est rendu publiquement que si le site fournit un `canonical_base_url` HTTPS.

## Permissions et audit

- lecture : permission `content.read` sur le contenu concerné ;
- modification : permission `seo.manage`.

Chaque création ou mise à jour incrémente `sites.data_revision` et ajoute un événement d’audit. Une version périmée est refusée au lieu d’écraser silencieusement une modification concurrente.

## Rendu public

`SeoHeadRenderer` génère :

- `<title>` ;
- meta description ;
- lien canonique ;
- directive `noindex,follow` si nécessaire ;
- balises Open Graph essentielles.

`PublicPageRenderer` accepte les métadonnées SEO comme dernier argument optionnel. L’absence de SEO personnalisé conserve le titre par défaut `Page — Site` et les balises Open Graph minimales.

## Sitemap

`SitemapBuilder` produit un sitemap XML à partir d’une liste bornée d’URLs HTTPS. Il :

- ignore les entrées marquées non indexables ;
- déduplique les URLs ;
- accepte une date `YYYY-MM-DD` pour `lastmod` ;
- refuse les URLs HTTP ou invalides.

Le builder ne dépend pas du stockage PDO : un adaptateur de site peut agréger les pages publiées et leurs métadonnées avant génération.

## Limites du premier lot

Le premier lot ne comprend pas encore :

- robots.txt dynamique ;
- données structurées Schema.org ;
- cartes Twitter/X spécifiques ;
- génération automatique d’images sociales ;
- interface MMW dédiée au SEO.

Ces extensions ne doivent pas être ajoutées au protocole MMW tant qu’un besoin générique n’est pas établi.
