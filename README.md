# aidd-level

Évalue le niveau **AI-Driven Development** d'un profil de développeur sur la grille AIDD
(❖ White → 🥇 Gold) et dit ce qui a mené là et comment monter d'un cran.

## Installation et lancement

**Hors du conteneur** :

```
make up
make exec
```

**Dans le conteneur** (après `make exec`) :

```
make evaluate arthur
```

`make evaluate` accepte plusieurs noms (`make evaluate arthur bohort`) ; le nom d'un profil
est résolu dans `profiles/`, puis `fixtures/`, sinon pris comme chemin tel quel. Tapée hors du
conteneur, la même commande fonctionne aussi (elle repasse par `docker compose exec`).

**Sans `make`** :

```
UID=$(id -u) GID=$(id -g) docker compose up -d --build
docker compose exec php composer install --no-interaction --no-progress
docker compose exec php bin/aidd-level evaluate profiles/arthur
```

**Image autonome** (sans conteneur vivant) :

```
docker build -t aidd-level . && docker run --rm aidd-level evaluate profiles/arthur
```

## Commandes

| Commande | Rôle |
|---|---|
| `make up` | construit l'image, la lance (`sleep infinity`), installe les dépendances |
| `make build` | alias de `make up` |
| `make exec` | shell interactif dans le conteneur |
| `make evaluate arthur [bohort ...]` | lance `bin/aidd-level evaluate` sur les profils nommés ; tapée dans le conteneur comme hors du conteneur |
| `make down` | arrête le conteneur |
| `make test` | PHPUnit |
| `make lint` | PHPStan |
| `make dup` | détection de duplication |
| `make demo` | évalue les quatre profils fournis (contrat de `docs/calibration.md`) |
| `make self` | évalue `profiles/self` |
| `make fmt FILE=…` | formate un fichier PHP |

Tout tourne dans un conteneur de développement vivant (PHP local insuffisant). Les commandes
autres que `up`, `build` et `down` exigent le conteneur déjà lancé, et échouent sinon avec
« Lance d'abord : make up ». Tapée dans le conteneur, `make evaluate` s'exécute directement ;
tapée hors du conteneur, elle vérifie elle-même le conteneur avant de s'y relayer — même
message d'erreur s'il ne tourne pas.

## La sortie

L'en-tête entier d'une sortie réelle (`make evaluate arthur`), quatre lignes logiques :

```
Niveau AIDD : 🥉 Copper — arthur (développeur indépendant)
❖ White  🔺 Red  🔹 Blue  🟢 Green  [🥉 Copper]  🥈 Silver  🥇 Gold
Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.
Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les deux ; le niveau
                 est le plus bas des quatre axes, un axe haut n'en compense pas un bas.
```

Puis, un bloc par ligne (docs/specs/06-sortie-et-progression.md § 5) :

- « Ce qui a mené là » — la synthèse des quatre axes, puis chaque axe qui bloque en détail :
  toutes ses preuves, chacune avec sa phrase et son pointeur.
- « Déjà acquis pour X » — les axes qui ne bloquent pas, avec le niveau qu'ils atteignent.
- « Comment monter d'un cran » — un geste par axe bloquant, ordonné par actionnabilité ; le
  premier porte la mention « à faire en premier » (la prochaine quête).
- « Notes » — trois familles fixes : écarté du calcul, pièces du dossier, qualité citée sans
  jugement.

Sortie complète, annotée bloc par bloc : `docs/sortie.md`.

## Pour aller plus loin

- `.claude/README.md` — l'architecture IA, le harnais.
- `src/README.md` — l'architecture du projet, hexagonale à trois couches.
- `profiles/README.md` — les entrées, ce que l'outil lit.
- `docs/sortie.md` — les sorties, ce que l'outil rend.
- `docs/methode.md` — la méthode complète : entrées, calcul, sortie.
