# Architecture cible

## Socle de référence

- Runtime : PHP 8.5 ou plus récent, compatible avec l'hébergement o2switch visé.
- Persistance de référence : MariaDB via PDO et migrations SQL versionnées.
- Noyau sans framework : dépendances faibles, déploiement mutualisé possible et portage facilité.
- Le cœur ne lit jamais l'identité depuis un payload HTTP. L'adaptateur MMW remet un contexte d'acteur après authentification et vérification du jeton d'appareil.
- Les tests du domaine restent séparés des tests d'intégration MariaDB. Les uploads et fichiers secrets restent hors du webroot.

Ce choix est le socle de développement initial, pas une promesse de déploiement avant validation de la version PHP et des extensions activées sur l'hébergement cible.

```text
Mon Manager Web (Android / iOS)
            |
            | Protocole MMW
            v
+-------------------------------+
|       Mon Manager Web CMS     |
|-------------------------------|
| Auth / rôles / permissions    |
| Modèles de contenu            |
| Brouillons / publication      |
| Révisions / restauration      |
| Médias / navigation / SEO     |
| Modules génériques            |
+-------------------------------+
            |
       Adaptateurs
   /        |         \
  v         v          v
PHP léger  WordPress  Translucide / autre
            |
       Connecteurs métier
   RDV / boutique / tickets / etc.
```

## Invariant principal

Le remplacement d'un outil métier ou du front public ne doit pas nécessiter un fork de MMW.
