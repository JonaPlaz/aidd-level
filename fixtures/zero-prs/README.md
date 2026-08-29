# Fixture `zero-prs`

## Ce qu'elle prouve

Le gate, étape 4 (`docs/specs/05-robustesse.md` § Gate) : `pull_requests.total ≥ 1`. À zéro,
rien n'est mesurable — c'est un échec de gate, pas un axe en confiance basse.

## Construction

`git-activity.json › pull_requests.total = 0`, aucun autre champ.

## Niveau attendu

**`non évaluable`** : `missingPrerequisite` cite `git-activity.json › pull_requests.total = 0`
et nomme l'absence de PR sur la période comme cause ; rien à mesurer.
