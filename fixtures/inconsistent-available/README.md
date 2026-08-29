# Fixture `inconsistent-available`

## Ce qu'elle prouve

La cohérence annoncé/présent (`docs/specs/05-robustesse.md` § Cohérence annoncé / présent),
dans les deux sens à la fois : une pièce annoncée absente, et une pièce présente non annoncée.
Aucun des quatre profils fournis n'a ce double écart.

## Construction

- `profile.json › available` liste `session.md` — le fichier n'existe pas dans le dossier.
- `declaratif.md` existe dans le dossier — il n'est pas listé dans `available`.

## Niveau attendu

Le niveau lui-même n'est pas le point de cette fixture (signaux ordinaires, statut « évalué »
attendu). Les notes attendues :

- « pièce annoncée, absente : session.md »
- « pièce présente, non annoncée : declaratif.md »
