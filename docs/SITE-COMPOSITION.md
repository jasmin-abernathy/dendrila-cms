# Composition d’un site

La configuration d’un site est construite avec quatre couches indépendantes :

1. **Kit fonctionnel** — structure de départ et modules utiles.
2. **Style visuel** — résultat graphique recherché.
3. **Identité de marque** — logo, couleurs et éléments propres au client.
4. **Modules activés** — fonctions réellement disponibles sur ce site.

```text
Association + Sobre et élégant + identité du client + actualités/agenda/documents = site final
```

Le même kit peut utiliser n’importe quel style. Inversement, un même style peut être partagé par tous les kits.
Le contrat JSON expérimental est défini dans `schemas/site-composition.schema.json`.

## Service interne et persistance

La migration 003 ajoute un brouillon, une copie publiée indépendante et un historique
par site. `PdoCompositionService` attend le contexte d'acteur authentifié existant et
les permissions `composition.manage` / `composition.publish`, accordées aux rôles
owner/admin par la migration et les données initiales.

`SiteComposition` reçoit les catalogues de styles, kits et modules **du serveur**.
Il refuse les propriétés inconnues, les modules absents ou dupliqués, le CSS libre,
les overrides hors vocabulaire et les URL de logo autres que HTTP(S).
Il vérifie le contraste texte/fond. Le front doit encore vérifier les autres paires
de couleurs avant d'utiliser les couleurs de marque (boutons, liens, focus).

Parcours de raccordement :
1. Construire et valider une composition avec les catalogues installés.
2. Enregistrer avec `saveDraft(actor, composition, expectedVersion)` (0 au départ).
3. Lire `draft(actor)` et afficher un aperçu privé.
4. Publier exactement la composition examinée avec sa version courante.
5. Le site public lit exclusivement `published(siteId)`.
6. Pour restaurer : lire `revision(actor, version)`, revalider avec les catalogues
   actuels, puis `restoreDraft`. La restauration ne republie pas automatiquement.

Chaque écriture verrouille le site, refuse une version périmée et enregistre
révision et audit dans la même transaction. Aucun réglage d'apparence ne modifie
la configuration des modules du protocole MMW v1.
Le service reste interne : les routes web, CSRF, aperçu privé, écran de choix
et invalidation du cache public doivent être raccordés au back-office existant.
Aucune nouvelle authentification autonome n'est ajoutée.

## Test MariaDB dédié

Après migration sur une base de test dédiée :
`CMS_COMPOSITION_TEST_DB=1 TEST_DB_DSN=... TEST_DB_USERNAME=... TEST_DB_PASSWORD=... php tests/composition-pdo-run.php`.

Le test crée un site au nom aléatoire puis nettoie uniquement ses lignes.
Il vérifie les refus d'accès, les versions périmées, le refus de publier une
composition différente de celle examinée, la séparation brouillon/public,
les snapshots immuables et la restauration en nouveau brouillon.
Il n'est pas inclus dans `composer test`, qui reste indépendant d'une base SQL.
