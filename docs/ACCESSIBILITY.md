# Confort de lecture

Le bouton « Accessibilité », identifié par un œil et un texte, se trouve en fin de page. Disponible sur les pages publiques et leurs aperçus, l’installation, le forum et les écrans d’administration (y compris la médiathèque autonome).

Le panneau propose une taille de texte de 100 à 200 %, une lecture simplifiée (contraste noir/blanc, police simple et suppression des décors du thème), l’espacement du texte, le soulignement des liens et la réduction des animations. Les préférences du système pour les animations restent prioritaires. Échap ferme le panneau et rend le focus au bouton. Les réglages sont immédiatement appliqués et réinitialisables.

Seule la clé locale `dendrila.reading.v1` mémorise ces choix dans le navigateur pour cette origine ; aucun appel réseau ni cookie de suivi. Si ce stockage est interdit, les réglages fonctionnent pour la page courante et un message le précise. Sans JavaScript ou sans prise en charge de la boîte de dialogue native, le contenu reste disponible, sans bouton inactif.

Les préférences ne modifient ni les contenus enregistrés, ni les droits, ni le protocole MMW. Ce panneau est indépendant du plugin WordPress Dendrila Accessibility Assistant, dont le diagnostic éditorial n’est pas porté ici. Il ne constitue pas une certification RGAA/WCAG : les alternatives pertinentes, la structure des contenus et les tests avec des aides techniques restent nécessaires.

Validation automatisée : `tests/accessibility-browser.cjs`, appelé depuis les parcours d’installation, d’administration et des six thèmes (1280 et 390 px). Contrôle du clavier, du retour du focus, des réglages, de la persistance, du reset, de la taille du panneau et du stockage interdit.
