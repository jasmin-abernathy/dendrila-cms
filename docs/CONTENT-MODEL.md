# Modèle de contenu

## Types V1

- `page` : contenu pérenne ;
- `article` : contenu daté ;
- `record` : fiche structurée générique ;
- `document` : fichier publié avec métadonnées.

## Champs de base

- `id`
- `type`
- `slug`
- `title`
- `status`
- `blocks`
- `seo`
- `created_at`
- `updated_at`
- `published_at`
- `revision`

## Blocs V1

- `text`
- `rich_text`
- `image`
- `gallery`
- `cta`
- `cards`
- `faq`
- `key_values`
- `embed_safe`

Aucun bloc ne peut fournir de JavaScript exécutable arbitraire à MMW.

## Statuts

- `draft`
- `review`
- `published`
- `archived`
