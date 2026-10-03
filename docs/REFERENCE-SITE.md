# Site pilote de référence

Le site pilote permet de valider le chemin complet **contenu structuré → preset visuel → design kit → HTML public** sans créer de base MariaDB ni déployer un site.

## Préparation

Cloner ou installer les deux dépôts côte à côte :

```text
repositories/
├── dendrila-cms/
└── dendrila-design-kits/
```

Depuis `dendrila-cms` :

```sh
composer install
php bin/build-reference-site.php ../dendrila-design-kits/catalog.json
```

La commande crée par défaut :

```text
build/reference-site/
├── index.html
├── README.txt
├── theme/
└── media/
```

Aucun fichier existant n'est supprimé : le dossier de sortie doit être absent ou vide.

## Affichage

Depuis `build/reference-site` :

```sh
php -S 127.0.0.1:8080 -t .
```

Puis ouvrir l'adresse locale indiquée par PHP.

Le serveur local est uniquement destiné à la prévisualisation. Le site de référence ne nécessite ni base de données ni configuration d'hébergement.

## Ce que le pilote vérifie

Le jeu `examples/design-reference-page.json` est volontairement générique. Il contient le même ensemble de blocs qui pourra ensuite être rendu avec les autres design kits :

- texte simple ;
- image avec masque et point focal ;
- cartes ;
- chiffres-clés ;
- paires clé/valeur ;
- galerie ;
- FAQ ;
- contenu externe bloqué avant consentement ;
- CTA.

Les médias de démonstration restent eux aussi neutres dans `examples/reference-media/`.

## Contrat de sécurité

`ReferenceSiteBuilder` :

- ne télécharge aucun code ;
- n'efface pas un dossier de sortie existant ;
- refuse les liens symboliques lors de la copie ;
- borne le nombre et la taille des fichiers copiés ;
- utilise uniquement un design kit déjà validé par `DesignKitCatalog` ;
- réutilise `PublicPageRenderer`, donc conserve les protections d'URL, d'échappement HTML et de consentement.

Le premier contrôle humain utile après ce build est donc visuel : vérifier le rendu sur petit et grand écran, au clavier et avec mouvement réduit.
