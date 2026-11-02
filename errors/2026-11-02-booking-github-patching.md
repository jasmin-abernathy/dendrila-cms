# 2026-11-02 — Modifications GitHub de l'installateur Booking

## Symptôme
Les premières tentatives de réécriture automatisée des tests de l'installateur ont été interrompues avant toute écriture GitHub : les chaînes de remplacement contenaient une indentation incorrecte ou une configuration de test qui ne correspondait pas au fichier réellement lu.

## Cause
Appariement de texte trop littéral sur des extraits non confirmés au caractère près (notamment 2 vs 3 espaces dans le script navigateur).

## Récupération
Récupération de la version complète des fichiers sur la branche de travail, inspection des segments exacts, assertion d'une occurrence unique avant mutation, puis création de commits de modifications cohérents. Aucun commit de test partiel n'a été laissé sur la branche.

## Prévention
Toujours baser les changements sur le contenu entier retourné par le connecteur, valider explicitement les chaînes et leur cardinalité avant de créer des blobs ou un arbre Git, puis contrôler le dernier SHA et ses CI.
