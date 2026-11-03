# Assemblage CMS, Booking et MMW — 3 novembre 2026

Le CMS public et le module Booking privé sont distribués ensemble uniquement via le dépôt privé : aucune copie du code propriétaire dans le dépôt public. Paquet construit depuis des sources épinglées, sans config privée ni données de test.

Incidents de préparation : chemin Csrf.php inexistant, retrouvé par rg sous SessionCsrf.php ; composer.lock absent avant composer install, création par Composer avant packaging ; runtime PHP local minimal sans Phar/Iconv, activation des extensions pour Composer. Le téléchargement Chromium local renvoyait une archive tronquée ; navigateur validé dans la CI et non présenté comme un test local réussi. Les sorties longues de lectures de règles ont été tronquées : relecture ciblée avant modification.

Les routes de développement ne traitaient pas /booking/ : ajout d'un routage optionnel uniquement lorsque le module installé fournit son index. Une session client distincte SameSite=Lax conserve la commande au retour GET du prestataire ; les mutations demeurent POST+CSRF. La session administrateur reste Strict.

Une suite locale a été lancée après composer --no-dev : autoload des doubles de tests absent. Relancer composer avec autoload-dev avant les tests, puis --no-dev uniquement pour construire la distribution. Les 20 suites passent après correction de cet environnement.
