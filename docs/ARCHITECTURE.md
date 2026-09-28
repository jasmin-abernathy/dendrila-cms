# Architecture cible

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
