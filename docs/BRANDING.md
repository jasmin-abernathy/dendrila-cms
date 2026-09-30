# Branding Dendrila CMS

## Noms des produits

- **Dendrila CMS** désigne le noyau serveur modulaire : contenus, médias, révisions, rôles, permissions, workflow éditorial, modules, kits fonctionnels, styles visuels et adaptateurs.
- **Mon Manager Web (MMW)** est l’application cliente de gestion. Elle peut piloter Dendrila CMS et d’autres sites qui implémentent le protocole MMW v1.
- **MMW v1 / Le Potager Site Manager Protocol v1** désigne le contrat technique historique. Ses routes, en-têtes, manifeste et noms de tables restent stables jusqu’à une migration de protocole décidée séparément.
- **Le Verger du Numérique** désigne la structure et l’écosystème, pas le nom du CMS.

## Règle de compatibilité

La marque Dendrila CMS ne change aucun identifiant technique déployé. En particulier, conserver `/.well-known/lepotager-site-manager.json`, `X-Lepotager-Protocol`, `X-Lepotager-Device-Token` et les tables `mmw_devices` / `mmw_idempotency` tant qu’une version ultérieure du protocole n’a pas défini une migration et une période de compatibilité.

Les noms `MMW` et `Dendrila` ne sont pas interchangeables : `mmw-*` désigne le client ou le protocole ; `dendrila-*` désigne le CMS et son système de rendu.
