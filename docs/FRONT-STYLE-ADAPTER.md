# Adaptateur de référence

`FrontStyleAdapter::htmlAttributes(style)` produit les attributs de la racine
`.mmw-site`. `CssTokenCompiler::compile(style, selector)` fournit les variables.
Charger `public/design/reference.css`, puis les variables compilées pour ce style.

Contrat HTML : `.mmw-section`, `.mmw-hero`, `.mmw-copy`, `.mmw-media`,
`.mmw-gallery`, `.mmw-card`, `.mmw-button`, `.mmw-illustration`.
Les classes sont communes aux six directions et les tokens restent déclaratifs.
Les images informatives conservent leur alt et ne disparaissent pas dans le style
minimal. Les illustrations décoratives portent aria-hidden.

La galerie statique utilise exactement le même contenu pour les six styles.
Les libellés et descriptions sont hors de l'aperçu. Le paysage SVG est local ;
aucune police distante, aucun script, aucune dépendance front n'est nécessaire.
Les variantes masonry utilisent une grille sans réordonner la lecture DOM ;
il s'agit d'un rythme de cartes, pas d'un algorithme de colonnes Pinterest.

Les couleurs de référence texte/fond/bouton sont fixes. Les couleurs de marque
validées sont persistées, mais ne sont pas injectées automatiquement par cet
adaptateur tant que toutes les paires interactives ne sont pas vérifiées.
Le focus reste visible et la préférence de mouvement réduit est respectée.

Limites de validation de cette passe : aucun PHP/Composer, MariaDB ou navigateur
exécutable dans l'environnement. Les tests PHP ajoutés sont à exécuter sous PHP 8.5.
Les contrôles de schéma, contenu identique et références locales sont exécutables
ici ; ils ne remplacent pas les tests navigateur mobile/clavier/zoom.

Régénérer la galerie avec `php bin/generate-design-gallery.php`. Le contenu commun est dans `examples/design-gallery/demo.html` ; ne pas recopier les attributs ou variables à la main.

La validation groupée de la branche `work/design-styles-composition` utilise PHP 8.5 et une base MariaDB dédiée via le workflow `design-composition.yml`. Elle ne valide pas le rendu navigateur.
