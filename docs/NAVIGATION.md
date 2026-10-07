# Navigation native

Dendrila CMS stocke désormais les menus comme une donnée de site générique, indépendante d’un design kit ou d’un client.

## Modèle

Un menu possède :

- un `menu_id` stable ;
- un libellé d’administration ;
- un emplacement (`primary`, `footer`, ou tout autre identifiant générique déclaré par le front) ;
- une liste ordonnée de liens ;
- une version utilisée pour la concurrence optimiste.

Un emplacement ne peut être occupé que par un menu à la fois pour un site.

Chaque lien contient uniquement `id`, `label`, `url` et `visible`. Les URLs acceptées sont les chemins racine (`/contact`), fragments (`#faq`), liens `mailto:` valides et URLs HTTPS. Les schémas exécutables et URLs protocol-relative sont refusés.

## Permissions

- `navigation.read` : consulter les menus dans l’administration ;
- `navigation.manage` : créer, modifier et supprimer.

Les mutations incrémentent `sites.data_revision` et écrivent un événement d’audit. Elles exigent la version courante ; une écriture obsolète échoue au lieu d’écraser silencieusement un changement concurrent.

## Front public

`PdoPublicNavigationSource` lit un emplacement sans exposer les champs d’administration. Il ne renvoie que les liens visibles sous la forme attendue par `PublicPageRenderer` :

```php
$navigation = (new PdoPublicNavigationSource($pdo))->items($siteId, 'primary');
```

Le renderer reste indépendant du stockage : un autre adaptateur peut fournir la même liste sans dépendre de PDO.

## Limites du premier lot

Le premier contrat est volontairement plat. Les sous-menus imbriqués, menus conditionnels, traductions par lien et édition directe dans Mon Manager Web ne sont pas introduits tant qu’un besoin générique n’est pas validé. Cette limite évite d’ajouter une nouvelle primitive au protocole MMW v1.
