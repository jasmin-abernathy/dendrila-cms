# Reprise de la validation MMW native

La branche d'association native était sans PR ouverte. Elle comprend déjà l'édition de pages, les conflits de version, la publication et la révocation. Ajout de vérifications PDO pour un compte suspendu et un jeton présenté au mauvais site, avant validation complète CI.

Les premiers chemins de tests/documentation supposés n'existaient pas. Résolution : inventaire rg --files puis lecture des fichiers réels. Utiliser un worktree distinct pour préserver les modifications locales de Booking/SQLite. La recette sur téléphone et le raccordement à la nouvelle branche SQLite restent distincts de cette validation MySQL.

Conflit détecté à l’ouverture de la PR : la base avait déjà repris les travaux natifs. Synchronisation avec la base et conservation des tests négatifs supplémentaires ; vérifier le diff final plutôt que présenter à nouveau les fonctionnalités déjà intégrées.
