# Dendrila Privacy — intégration front native

Le noyau fournit maintenant un rendu de consentement indépendant de WordPress, d'un framework et d'un constructeur visuel.

## Composants

- `ConsentFrontend` relie le manifeste sûr produit par `PrivacyService` au rendu HTML.
- `ConsentRenderer` produit la barre ou l'encart accessible, en français ou en anglais.
- `ConsentResourceRenderer` produit des scripts, feuilles de style et iframes **différés avant consentement**.
- `public/assets/privacy-consent.js` gère le choix local, le fingerprint, l'expiration, la réouverture et l'activation des ressources.
- `public/assets/privacy-consent.css` fournit un style neutre, accessible et surchargeable par variables CSS.
- `PrivacyDocumentCollector` et `PrivacyScanRunner` définissent le contrat du futur collecteur du site public.

## Règles de consentement

Le comportement natif reprend les contraintes de Dendrila Privacy :

- aucune catégorie facultative précochée ;
- « Tout accepter » et « Tout refuser » utilisent le même niveau visuel ;
- fermer le dialogue ne crée aucun consentement ;
- sans choix valide, les ressources différées restent bloquées ;
- « Gérer mes choix » permet de rouvrir les préférences ;
- un changement de fingerprint invalide l'ancien choix ;
- une révocation d'une catégorie déjà chargée provoque un rechargement afin que les scripts précédemment exécutés ne restent pas actifs ;
- le choix est local au navigateur et aucune adresse IP n'est stockée par le CMS.

## Rendu du composant

L'adaptateur du site appelle :

```php
$frontend = new ConsentFrontend($privacyService);
echo $frontend->render($siteId, '/assets', 'fr');
```

Le rendu est vide si le consentement natif est désactivé ou si aucun service facultatif n'a été détecté.

Les assets doivent être servis depuis le webroot `public/`. Une URL HTTPS de CDN privé peut être fournie à la place de `/assets`.

## Différer une ressource

Une ressource facultative ne doit **jamais** conserver son vrai `src` ou `href` dans le HTML initial.

### Script externe

```php
echo $resources->script(
    'statistics',
    'https://analytics.example.test/script.js',
    async: true,
);
```

Le HTML produit utilise `type="text/plain"` et `data-dendrila-src`. Le navigateur ne télécharge pas le script avant autorisation.

### Script inline

```php
echo $resources->inlineScript('marketing', $javascript);
```

Le code est encodé en base64 dans un attribut et n'est recréé comme vrai `<script>` qu'après consentement.

### Contenu externe

```php
echo $resources->iframe(
    'external_media',
    'https://video.example.test/embed/123',
    'Présentation vidéo',
);
```

L'iframe reste sans `src`. Un placeholder explique le blocage et permet d'autoriser explicitement la catégorie.

### Feuille de style facultative

```php
echo $resources->stylesheet('functional', '/optional/widget.css');
```

## API navigateur

Le front expose :

```js
DendrilaPrivacyConsentAPI.open();
DendrilaPrivacyConsentAPI.openPreferences();
DendrilaPrivacyConsentAPI.close();
DendrilaPrivacyConsentAPI.getChoice();
DendrilaPrivacyConsentAPI.saveChoice({ statistics: false, external_media: true });
DendrilaPrivacyConsentAPI.allows('statistics');
```

Les événements publics sont :

- `dendrila-privacy:consent-opened` ;
- `dendrila-privacy:consent-closed` ;
- `dendrila-privacy:consent-updated`.

## Collecte pour les scans

Le noyau ne crawle toujours pas arbitrairement un site.

Un adaptateur concret implémente `PrivacyDocumentCollector` et retourne des documents bornés :

```php
[
    ['id' => 'home', 'url' => 'https://example.test/', 'content' => $renderedHtml],
]
```

`PrivacyScanRunner` enchaîne ensuite :

> collecte → inspection → enregistrement du scan et audit

Le premier collecteur concret dépend du moteur de rendu public retenu par Dendrila. Cette dépendance reste volontairement hors du noyau générique.
