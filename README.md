# aidd-level

Évalue le niveau **AI-Driven Development** d'un profil de développeur sur la grille AIDD
(❖ White → 🥇 Gold). La sortie dit trois choses : le niveau atteint, les faits qui le
justifient (chacun avec sa preuve), et ce qu'il faudrait changer pour passer au niveau
suivant.

Les profils évalués sont ceux fournis par le sujet, copiés tels quels dans `profiles/`
(licence et provenance : `profiles/ATTRIBUTION.md`).

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

## La sortie

L'en-tête d'une sortie réelle (`make evaluate arthur`) :

```
Niveau AIDD : 🥉 Copper — arthur (développeur indépendant)
❖ White  🔺 Red  🔹 Blue  🟢 Green  [🥉 Copper]  🥈 Silver  🥇 Gold
Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.
Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les deux ; le niveau
                 est le plus bas des quatre axes, un axe haut n'en compense pas un bas.
```

Puis quatre blocs :

- « Ce qui a mené là » — l'état des quatre axes en une ligne, puis le détail de chaque axe
  qui bloque : chaque fait en une phrase, avec sa preuve (`fichier › champ = valeur`).
- « Déjà acquis pour X » — les axes qui suffisent déjà pour le niveau suivant.
- « Comment monter d'un cran » — un geste concret par axe bloquant, le premier marqué
  « à faire en premier ».
- « Notes » — ce qui a été vu mais ne décide pas : signaux écartés, qualité citée sans
  jugement, pièces du dossier.

Sortie complète commentée : `docs/sortie.md`. Méthode de calcul (quelles données, quels
seuils, pourquoi) : `docs/methode.md`.

## Pour aller plus loin

- `docs/methode.md` — la méthode complète : entrées, calcul, sortie.
- `docs/sortie.md` — les sorties, ce que l'outil rend.
- `profiles/README.md` — les entrées, ce que l'outil lit.
- `src/README.md` — l'architecture du projet, hexagonale à trois couches.
- `.claude/README.md` — l'architecture IA, le harnais.
