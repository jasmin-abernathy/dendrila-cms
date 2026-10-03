# Importer Dendrila Privacy depuis WordPress

Dendrila CMS peut reprendre les données utiles du plugin WordPress Dendrila Privacy sans installer WordPress dans le CMS et sans modifier le plugin source.

## Données reprises

L'importeur prend en charge :

- activation et présentation du consentement ;
- durée de mémorisation et lien de gestion des choix ;
- fréquence des analyses ;
- profil documentaire légal complet ;
- traitements ;
- sources/services indirects ;
- dernier scan technique, converti en constats natifs lorsque les données sont présentes.

Les identifiants de pages WordPress, l'injection Divi/Elementor, les anciens réglages d'adaptateurs WordPress et le style `inherit/neutral` du plugin ne sont pas copiés. Ils sont signalés comme ignorés dans le résultat d'import.

## Export depuis WordPress

Sur l'installation WordPress source, avec WP-CLI :

```sh
wp eval 'echo wp_json_encode(array(
  "pixel_trackers_manager_settings" => get_option("pixel_trackers_manager_settings", array()),
  "pixel_trackers_manager_legal_profile" => get_option("pixel_trackers_manager_legal_profile", array()),
  "pixel_trackers_manager_scan_current" => get_option("pixel_trackers_manager_scan_current", array())
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);' > dendrila-privacy-export.json
```

Le fichier contient des informations de configuration et potentiellement des éléments du profil documentaire. Il doit être traité comme un fichier administratif privé.

## Import dans Dendrila CMS

Après les migrations et la création du site cible :

```sh
php bin/import-wordpress-privacy.php <site_id> <actor_id> dendrila-privacy-export.json
```

L'acteur doit disposer de `privacy.manage`. Si le fichier contient un scan, il doit aussi disposer de `privacy.scan`.

L'import s'exécute dans une transaction. Un hash du fichier logique produit une clé d'idempotence déterministe : relancer exactement le même export pour le même site et le même acteur renvoie le résultat précédent sans dupliquer le scan ni incrémenter à nouveau la révision du site.

## Conversion du scan

Les catégories WordPress sont ramenées vers les catégories génériques du noyau :

- mesure d'audience / analyse comportementale → `statistics` ;
- marketing et e-mailing de suivi → `marketing` ;
- contenus embarqués / cartographie / ressources externes → `external_media` ;
- sécurité, formulaires, réservation, paiement et messagerie → `functional` ;
- gestion de balises ou catégorie non reconnue → `unknown`.

Un service marqué comme bloqué par le consentement dans WordPress reste facultatif après import. reCAPTCHA reste volontairement contextuel et n'est pas reclassé automatiquement comme traceur facultatif.

Les preuves importées sont conservées comme provenance technique ; elles ne deviennent jamais une certification juridique.
