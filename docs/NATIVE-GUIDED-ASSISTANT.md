# Assistant d'orientation réutilisable — Dendrila

Le bloc natif `guided_assistant` est disponible dans **tous les thèmes** depuis
l'éditeur de pages : **Ajouter un bloc → Assistant d’orientation**.
Il ne dépend d'aucun domaine, fournisseur de chatbot, compte externe ou IA.

Le propriétaire choisit librement un titre : « Le Jardinier », « L'Artisan »,
« Assistant Prestadmin », etc. Il modifie une introduction et une liste
d'options, chacune contenant :

- un titre de situation ;
- une recommandation affichée après sélection ;
- un lien de prochaine étape, relatif au site ou en HTTPS.

La présentation est rendue en HTML sémantique `details/summary`, donc
**utilisable au clavier et sans JavaScript**. Un script local facultatif ferme
les autres choix quand un choix s'ouvre. Les styles communs et le thème
d'origine fournissent la version mobile, le focus visible et le mode lisible.

L'étape de conseil reste **locale au navigateur** : aucune réponse ni adresse
e-mail n'est collectée, aucun événement n'est transmis automatiquement.
Un clic sur le lien final peut ensuite ouvrir une page, un formulaire
ou un autre outil distinct, selon la configuration de la personne qui
administre le site.

Ce composant fournit un **premier niveau d'orientation**, pas le moteur
complet à 2–4 étapes du Jardinier du Potager. La transposition fidèle du
moteur conditionnel avec des branches, récapitulatifs et formulaire de
transmission sécurisé demandera une intégration supplémentaire. En
particulier, le bloc ne se présente jamais comme un chatbot IA actif.

## Présentation illustrée native

Le bloc `image_text` affiche un texte et une image dans une section éditable.
La photo peut provenir de la médiathèque publique, d'un actif local ou
d'une URL HTTPS. **Préférer une image hébergée sur le site**, pour la vie
privée et la sobriété. L'image du bandeau est gérée séparément par
`hero_image_url` dans **Administration → Apparence** : elle est optionnelle,
n'impose pas de média à un thème et reste modifiable par le propriétaire.

Les six thèmes peuvent afficher ces blocs. Le thème « Très minimal »
fournit en plus un modèle d'accueil contenant une composition de services,
des bénéfices, une présentation avec photo locale et un exemple d'assistant.
Les autres thèmes permettent d'ajouter ces composants sans modifier leur code.

## Parcours facultatif lors de l'installation

Au choix des modules, « Formulaire classique uniquement » est sélectionné par défaut. L'utilisateur peut choisir d'ajouter un « Parcours guidé », sur **n'importe lequel des six thèmes**, et sa présentation : cartes, liste ou compacte. Le formulaire de contact classique reste disponible. Le choix n'exige ni IA ni connexion à un service externe.

Les futurs sites « Très minimal » conservent les blocs inspirés de la mise en page professionnelle de Prestadmin, sans assistant forcé et sans bouton mort. Le nom du bloc dans le back-office est « Parcours guidé », librement personnalisable. « Le Jardinier » reste seulement la déclinaison du Potager. Les sites existants ne sont pas migrés automatiquement.
