# Oldweb-kit · thèmes rétro et fond Ripple

## Portée et état

Première implémentation **originale** d'un fond Ripple décoratif, inspirée du principe général des anciens sites Flash et de l'effet de fond `ripple` identifié sur Ragekit. Aucun code ni média de Cargo/Ragekit n'est réutilisé.

- Rendu WebGL 1 dans un `canvas` superposé à une **image fixe** déjà fournie par la médiathèque.
- Survol/pointeur : déplacement des ondulations ; animation lente autonome sur écran tactile.
- Pause/reprise au clavier, arrêt onglet masqué, réduction de fréquence, résolution plafonnée.
- Repli **sans WebGL** ou si `prefers-reduced-motion: reduce` / économiseur de données : seule l'image fixe apparaît.
- Aucun CDN, bibliothèque JS ni média chargé depuis un tiers.
- Aucune animation ajoutée aux pages existantes : **activation explicite nécessaire**.

## Démonstration locale

La maquette Oldweb retenue est [Canal 57](OLDWEB-FIXED-PORTAL.md). La commande ci-dessous reste disponible pour vérifier le moteur Ripple sur le site de référence.

Avec PHP 8.5+, Composer et un catalogue de `dendrila-design-kits` compatible (voir `README.md`) :

```sh
composer install
php bin/build-reference-site.php /chemin/design-kits/catalog.json build/ripple-demo --ripple
cd build/ripple-demo
php -S 127.0.0.1:8080 -t .
```

Ouvrir `http://127.0.0.1:8080/ripple-preview.html` pour voir la démonstration rétro (visuel SVG créé dans ce dépôt). La page `index.html` du site de référence dispose du même fond, activé à titre de démonstration.

La commande sans `--ripple` conserve le comportement existant : pas de script Ripple ajouté.

## Activation depuis une présentation publique contrôlée

Le renderer `PublicPageRenderer` attend la configuration **facultative** suivante dans le tableau `$presentation` :

```php
$presentation['backdrop'] = [
    'type' => 'ripple',     // seul preset autorisé actuellement
    'media_id' => 'hero',   // clé existante du tableau $mediaUrls
    'speed' => 0.85,        // 0.25 à 2.0
    'intensity' => 0.75,    // 0 à 1.0
];
```

Le média source doit être une URL d'image publique **locale** (chemin absolu commençant par `/`, sans requête ou traversée de chemin), déjà présente dans le tableau `$mediaUrls`. Les paramètres non conformes sont rejetés explicitement. L'import des ZIP Oldweb-kit ne fournit jamais des scripts ou shaders exécutables.

**Important :** pour un site dynamique, l'installateur doit servir `public/assets/oldweb-ripple.css` et `public/assets/oldweb-ripple.js` à `/assets/`. Le constructeur de référence copie ces fichiers automatiquement en mode Ripple.

## Accessibilité et sobriété

Le bouton pause/reprise se situe en bas à droite, avec zone tactile >= 44 px et `:focus-visible`. Les mouvements réduits ne créent pas de contexte WebGL. Le fond n'a pas de contenu informatif, n'intercepte pas les événements de navigation et ne crée aucune requête vers un tiers. La visibilité et la baisse de fréquence limitent le travail inutile ; **aucune économie d'énergie ou note EcoIndex n'est revendiquée sans mesure**.

### Tests

```sh
php tests/oldweb-ripple-run.php
php tests/front-run.php
php tests/reference-site-run.php
node --check public/assets/oldweb-ripple.js
```

La suite PHP couvre les contrats et les chemins existants. Vérifier aussi le moteur dans un navigateur avec WebGL actif, puis avec mouvement réduit et en simulant une perte de contexte.

Mesurer CPU/GPU, batterie et EcoIndex sur un appareil modeste avant toute affirmation sur la consommation. Les autres préréglages de fond restent à étudier.
