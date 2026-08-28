# ROADMAP

Plan ordonné, dérivé des spécifications de `docs/specs/`. Fichier **append-only** : une ligne
par chantier, l'état se met à jour en ajoutant une ligne, jamais en réécrivant.

Deux chantiers sont simultanés quand leurs specs ne partagent aucun fichier de sortie et
qu'aucun ne dépend du résultat de l'autre. Le nombre de fronts ouverts se déduit de la colonne
« dépend de », il ne se décrète pas.

| # | Chantier | Spec | Dépend de | Sorties | Issue | État |
|---|---|---|---|---|---|---|
