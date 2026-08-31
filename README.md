# aidd-level

Évalue le niveau **AI-Driven Development** d'un profil de développeur sur la grille AIDD
(❖ White → 🥇 Gold). La sortie dit trois choses : le niveau atteint, les faits qui le
justifient (chacun avec sa preuve), et ce qu'il faudrait changer pour passer au niveau
suivant.

Les profils d'exemple de `profiles/` sont fournis par le sujet, copiés tels quels (licence
et provenance : `profiles/ATTRIBUTION.md`) ; l'outil évalue aussi tout dossier au même
format — un chemin, une fixture, ou le dépôt lui-même (`make self`).

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
| `make self` | évalue le dépôt `aidd-level` lui-même (profil `profiles/self`) |
| `make test` | lance les tests (PHPUnit) |
| `make lint` | analyse statique du code (PHPStan) |
| `make dup` | vérifie qu'aucun bloc de code n'est dupliqué dans `src/` |
| `make fmt FILE=…` | met en forme un fichier PHP |
| `make down` | arrête le conteneur |

Tout tourne dans Docker : le PHP local n'est pas requis, seul le conteneur l'est.

## Ce que l'outil lit

Tout le dossier du profil est lu. Deux pièces notent : `git-activity.json` (les mesures d'où
sortent les quatre axes) et `repo-context/` (la preuve des fichiers de contexte et des boucles).
`profile.json` donne l'identité et la liste des pièces annoncées, `sonar-measures.json` est cité
sans jugement. `session.md` et `code/` sont inventoriés mais jamais notés ; `declaratif.md`
n'entre jamais dans le calcul — c'est le choix du sujet : seules les données agrégées et
vérifiables décident. Détail : `docs/methode.md`.

## La sortie

Le début d'une sortie réelle (`make evaluate arthur`) :

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
  | Taille       | 🥇 Gold                   | ses PR touchent d'habitude 29 fichiers     |
  +--------------+---------------------------+--------------------------------------------+

Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les deux — c'est
                 l'axe le plus bas qui donne le niveau attribué.
```

Puis, dans l'ordre de lecture :

- le tableau « Niveau par axe » — les quatre axes, leur niveau, un constat court ;
  `← niveau global` marque la rangée dont le niveau est le niveau final ;
- « Niveau suivant » — le niveau d'après et ce qu'il faut pour y monter ;
- « Déjà acquis pour X » — les axes qui suffisent déjà pour le niveau suivant ;
- « Ce qui manque pour X » — un geste concret par axe qui limite, le premier marqué
  « à faire en premier » ;
- « Les preuves des axes qui limitent » — chaque fait en une phrase, avec son reçu
  vérifiable (`vérifier dans : fichier › champ = valeur`).

Sortie complète commentée : `docs/sortie.md`. Méthode de calcul (quelles données, quels
seuils, pourquoi) : `docs/methode.md`.

## Pour aller plus loin

- `docs/methode.md` — la méthode complète : entrées, calcul, sortie.
- `docs/sortie.md` — les sorties, ce que l'outil rend.
- `profiles/README.md` — les entrées, ce que l'outil lit.
- `src/README.md` — l'architecture du projet, hexagonale à trois couches.
- `.claude/README.md` — l'architecture IA, le harnais.
