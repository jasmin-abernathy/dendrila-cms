# Dendrila CMS — façade intégralement sur mesure

## Deux approches complémentaires

1. **Thèmes prêts à l'emploi** : l'installation utilise les six directions visuelles incluses.
2. **Façade libre (adaptateur)** : le développeur conserve ses templates PHP, ses feuilles de style, son JavaScript et ses composants métiers. Dendrila gère les contenus, la publication, les médias (si activés), le menu et le SEO.

`Dendrila\\Cms\\Runtime\\CustomFrontendBridge` est la première brique de cette seconde approche. **Il ne charge aucun thème** et ne rend jamais le HTML à la place du développeur.

### API PHP

Après inclusion du bootstrap privé et connexion PDO via l'installation Dendrila :

```php
$bridge = new \Dendrila\Cms\Runtime\CustomFrontendBridge($pdo, 'default');
$page = $bridge->page('home'); // null si brouillon/archivée/inexistante
if ($page === null) { http_response_code(404); exit; }
$seo = $bridge->seoHead($page, 'Mon site', 'https://exemple.fr');
$menu = $bridge->navigation();
$cards = $bridge->component($page, 'cards', 'Nos prestations');
$media = $bridge->media();
```

Le template de l'entreprise décide librement de la structure du DOM, des classes CSS et des composants graphiques. Pour du texte HTML, utiliser `CustomFrontendBridge::escape($value)`; pour une URL de lien, `::href($url)`. Ne **jamais** injecter une valeur éditable directement dans du PHP ou du HTML non échappé.

### Séparation des responsabilités

| Dendrila | Développeur |
| --- | --- |
| Authentification et permissions des éditeurs | Identité visuelle et responsive |
| Contenus, révisions, publication | HTML/PHP des pages et composants |
| Médiathèque et textes alternatifs | Composition graphique, CSS et JS |
| Menus et SEO structurés | Routes et éléments métier |
| Réglages de l'administration | Paiements, formulaires spécialisés, parcours métier |

**L'adaptateur ne transforme pas automatiquement un site statique en site dynamique.** Une page HTML existante doit être raccordée côté serveur à un template PHP ou à une publication statique contrôlée. Ce raccordement nécessite une étape dédiée, testée avant toute mise en production.

### Prestadmin : périmètre de la première intégration

Le dépôt `prestadmin57-site` conserve son front-end, `/projet/`, `/facturation-electronique/`, `/paiement/`, `/outils/siret/` et `/espace-prestadmin/`. Ces modules et leurs sessions **ne doivent pas être remplacés** par l'administration Dendrila.

Le raccordement se fera depuis une installation Dendrila privée sur un espace de test, par des templates PHP qui lisent exclusivement les contenus publiés. Le développeur associera explicitement chaque zone éditable à un composant du CMS (titre, présentation, cartes de services, etc.). Pas de reprise automatique de formulaires ni de transferts de paiement.

### État actuel

- Adaptateur de lecture natif et tests : en cours d'implémentation.
- Installation « design sur mesure » dans l'assistant : **à raccorder** ; la présence de cet adaptateur ne la rend pas disponible.
- Prestadmin en production : **inchangé** ; son back-office Dendrila n'est pas installé.
- BMH : conserve son ancien mini-CMS compatible avec le protocole MMW v1 ; ne pas le présenter comme une installation actuelle du noyau sans migration effective.
