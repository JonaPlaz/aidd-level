# aidd-level

Évalue le niveau **AI-Driven Development** d'un profil de développeur sur la grille AIDD
(❖ White → 🥇 Gold). La sortie dit trois choses : le niveau atteint, les faits qui le
justifient (chacun avec sa preuve), et ce qu'il faudrait changer pour passer au niveau
suivant.

Les profils d'exemple de `profiles/` sont fournis par le sujet, copiés tels quels (licence
et provenance : `profiles/ATTRIBUTION.md`) ; l'outil évalue aussi tout dossier au même
format — un chemin, une fixture, ou le dépôt lui-même (`make self`).

**Démonstration en vidéo (une minute) : [docs/demo.mp4](docs/demo.mp4).**

## Installation et lancement

```
make up            # une fois : construit et lance le conteneur, installe les dépendances
make evaluate arthur
```

`make evaluate` évalue un ou plusieurs profils : `make evaluate arthur bohort`. Le nom est
cherché dans `profiles/`, puis dans `fixtures/`, sinon pris comme chemin. La commande se tape
où l'on veut : hors du conteneur, elle se relaie d'elle-même dans le conteneur — qui doit
tourner (`make up` d'abord, sinon le message « Lance d'abord : make up » le rappelle).

## Commandes

| Commande | Effet |
|---|---|
| `make up` | construit l'image, lance le conteneur (il reste ouvert), installe les dépendances |
| `make exec` | ouvre un shell dans le conteneur |
| `make evaluate arthur bohort` | évalue les profils nommés |
| `make demo` | évalue les quatre profils fournis par le sujet |
| `make self` | le dépôt se note lui-même : `profiles/self` est fabriqué depuis ses propres commits et PRs, `make self` rend son verdict |
| `make test` | lance les tests (PHPUnit) |
| `make lint` | analyse statique du code (PHPStan) |
| `make dup` | vérifie qu'aucun bloc de code n'est dupliqué dans `src/` |
| `make fmt FILE=…` | met en forme un fichier PHP |
| `make down` | arrête le conteneur |

Tout tourne dans Docker : le PHP local n'est pas requis, seul le conteneur l'est.

## Ce que l'outil lit

Toutes les pièces du dossier sont inventoriées, deux seulement sont lues pour noter : `git-activity.json` (les mesures d'où
sortent les quatre axes) et `repo-context/` (la preuve des fichiers de contexte et des boucles).
`profile.json` donne l'identité et la liste des pièces annoncées, `sonar-measures.json` est cité
sans jugement. `session.md` et `code/` sont inventoriés mais jamais notés ; `declaratif.md`
n'entre jamais dans le calcul — c'est le choix du sujet : seules les données agrégées et
vérifiables décident. Détail : `docs/methode.md`.

## La sortie

La sortie réelle, complète (`make evaluate arthur`), recopiée telle quelle :

```
arthur — développeur indépendant
Niveau AIDD : 🥉 Copper
Échelle des niveaux : ❖ White  🔺 Red  🔹 Blue  🟢 Green  [🥉 Copper]  🥈 Silver  🥇 Gold
Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.

Niveau par axe
  +--------------+---------------------------+--------------------------------------------+
  | Axe          | Niveau                    | Constat                                    |
  +--------------+---------------------------+--------------------------------------------+
  | Harness      | 🥉 Copper ← niveau global | context engineering acquis                 |
  | En parallèle | 🥇 Gold                   | d'habitude 4 chantiers menés en même temps |
  |              |                           | (médiane)                                  |
  | Intervention | 🥉 Copper ← niveau global | d'habitude 1 corrections après l'ouverture |
  |              |                           | d'une PR (médiane)                         |
  | Taille       | 🥇 Gold                   | ses PR touchent d'habitude 29 fichiers     |
  |              |                           | (médiane)                                  |
  +--------------+---------------------------+--------------------------------------------+

Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les deux — c'est
                 l'axe le plus bas qui donne le niveau attribué.


Déjà acquis pour 🥈 Silver
--------------------------
  En parallèle — 🥇 Gold
    d'habitude 4 chantiers menés en même temps (médiane) : au moins le seuil de 3 de Gold.
      git-activity.json — l'activité git du profil, déjà agrégée : PR, commits, branches et fichiers
                          de contexte, sur la période du fichier.
      vérifier dans : git-activity.json › parallelism.median_concurrent_branches = 4

  Taille — 🥇 Gold
    ses PR touchent d'habitude 29 fichiers (médiane) : taille XL (> 20), satisfait de Green à Gold.
      vérifier dans : git-activity.json › pull_requests.median_files_changed = 29


Ce qui manque pour 🥈 Silver
----------------------------
  1. Harness (à faire en premier) — ajouter une relance automatique bornée (N essais visibles) dans
                                    la CI ou un script, sur une commande du projet
     Ce qui le prouvera : repo-context/ › bounded retry
     Aujourd'hui : repo-context/ › bounded retry = none found

  2. Intervention — automatiser la validation (tests, lint, duplication) pour qu'aucune reprise
                    humaine ne soit nécessaire après ouverture
     Ce qui le prouvera : pull_requests.median_correction_commits_after_open


Les preuves des axes qui limitent
---------------------------------
  Harness — 🥉 Copper : l'un des deux axes qui limitent le niveau
    context engineering acquis : un fichier mémoire est versionné à la racine du dépôt ; Green et
    Copper demandent en plus une règle, un agent ou un hook versionné.
      vérifier dans : git-activity.json › context_files.agents_md = true
    behavior acquis : 0 règles, 4 skills, 0 hooks, 2 agents ; au moins un compteur non nul, c'est le
    « behavior » que Green et Copper demandent, Silver et Gold demandent en plus une boucle bornée.
      vérifier dans : git-activity.json › context_files = {rules:0, skills:4, hooks:0, agents:2}
    behavior : preuve structurelle trouvée dans repo-context/
      repo-context/ — la copie des fichiers de configuration IA trouvés à la racine du dépôt.
      vérifier dans : repo-context/.claude/agents/migration-auditor.md › file = present
    boucles : aucune relance bornée trouvée
      vérifier dans : repo-context/ › bounded retry = none found

  Intervention — 🥉 Copper : l'un des deux axes qui limitent le niveau
    d'habitude 1 corrections après l'ouverture d'une PR (médiane) : aux étapes clés (0 < médiane <
    2).
      vérifier dans : git-activity.json › pull_requests.median_correction_commits_after_open = 1
    taille de l'échantillon de PR (plancher 5 ; plancher « jamais » 12)
      vérifier dans : git-activity.json › pull_requests.total = 154
```

Sortie complète commentée : `docs/sortie.md`. Méthode de calcul (quelles données, quels
seuils, pourquoi) : `docs/methode.md`.

## Pour aller plus loin

- `docs/methode.md` — la méthode complète : entrées, calcul, sortie.
- `docs/sortie.md` — les sorties, ce que l'outil rend.
- `profiles/README.md` — les entrées, ce que l'outil lit.
- `src/README.md` — l'architecture du projet, hexagonale à trois couches.
- `.claude/README.md` — l'architecture IA, le harnais.
- `docs/demo.mp4` — la démonstration en vidéo (une minute) : le README, puis une évaluation
  réelle au terminal.
