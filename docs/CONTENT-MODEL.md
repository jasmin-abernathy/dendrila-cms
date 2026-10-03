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
Les structures de blocs acceptent uniquement leurs clés déclarées ; les URL sont limitées à HTTP(S). Le texte `rich_text` est une donnée, jamais du HTML de confiance : les rendus publics doivent l'échapper ou appliquer un assainisseur à liste blanche.

Un bloc `image` peut conserver des métadonnées de présentation **non destructives** : `shape` (`auto`, `organic-soft`, `organic-tall`, `arch`, `circle`, `rounded`, `none`) et `focal_x` / `focal_y` entre 0 et 100. Le fichier média original n'est pas recadré pour un thème : l'adaptateur front traduit ces valeurs en masque et point focal.

## Statuts

- `draft`
- `review`
- `scheduled`
- `published`
- `archived`

`scheduled` conserve `publish_at` en UTC et ne devient `published` qu'après validation par le worker planifié. Une programmation créée sans revue obligatoire doit repasser en revue si la politique du site l'exige avant l'échéance.
