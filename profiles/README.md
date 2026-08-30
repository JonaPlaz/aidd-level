# Les entrées de l'outil (`profiles/`)

Panneau descriptif : ce que l'outil lit dans un dossier de profil. Les règles d'exclusion et
leur pourquoi restent en spec — ce panneau nomme et pointe. Sources :
`docs/specs/00-vue-ensemble.md` § 3, `docs/specs/03-axe-intervention.md`,
`docs/specs/05-robustesse.md`.

## Ce qu'est un profil

Un dossier de mesures pré-agrégées fournies par le sujet — jamais un dépôt git à cloner, et
jamais lu comme tel.

## Les huit pièces possibles

- `profile.json` — lu (identité, cohérence de la colonne `available`).
- `git-activity.json` — lu ; c'est le fichier depuis lequel les quatre axes se calculent.
- `repo-context/` — lu (preuve de l'axe Harness, détection des boucles).
- `sonar-measures.json` — lu, cité sans jugement (prérequis qualité, hors calcul).
- `pull-requests.json` — inventorié pour la cohérence `available`, jamais comparé à une
  médiane.
- `declaratif.md` — sa seule présence est notée ; son contenu n'entre dans aucun calcul.
- `code/` — inventorié, jamais ouvert.
- `session.md` — inventorié, jamais ouvert.

Le *pourquoi* de chaque exclusion : `docs/specs/00-vue-ensemble.md` § 3 et
`docs/specs/03-axe-intervention.md`.

## Ce que contient ce dépôt

Les profils fournis par le sujet, calibrés (`arthur`, `bohort`, `leodagan`, `perceval`) et
non calibrés (`venec`, `lancelot`) — attribution de licence dans `profiles/ATTRIBUTION.md`.
`profiles/self/` est fabriqué depuis ce dépôt par `scripts/self-profile.py`, régénéré quand ce
qu'il lit change.

## Où les champs sont lus

`src/Infrastructure/Profile/` — un lecteur dédié par mesure parsée : `GitActivityReader`,
`PullRequestsReader`, `RepoContextReader`, `SonarMeasuresReader` (`JsonFile` n'étant que le
décodage JSON). Le reste vit dans `DirectoryProfileSource` : il lit `profile.json` lui-même
(identité, `available`), applique le gate d'entrée, note la seule présence de `declaratif.md`,
et inventorie `code/` et `session.md` sans jamais les ouvrir.

## `fixtures/`

Des dossiers fabriqués pour couvrir les cas que les profils fournis par le sujet ne prouvent
pas — un cas par dossier.

## Champ absent, jamais zéro

Un champ qui décide un axe et qui manque de `git-activity.json` n'est jamais pris pour zéro :
la règle reste en `docs/specs/05-robustesse.md`, section « Signal absent ».
