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

Quatre premières lignes d'une sortie réelle (`make evaluate arthur`) :

```
❖ 🔺 🔹 🟢 [🥉] 🥈 🥇
axe bloquant : Harness et Intervention (ex æquo)
🥉 Copper — arthur (développeur indépendant)
Niveau atteint : Copper · niveau visé : Silver
```

Puis, un bloc par ligne :

- « Ce qui a mené là » — le fait, avec son pointeur, pour chaque axe qui plafonne.
- « Acquis » — les axes qui satisfont déjà le niveau visé.
- « Comment monter d'un cran » — le geste ordonné vers le niveau suivant.
- « Prochaine quête » — le geste isolé, le plus actionnable des précédents.
- « Notes » — ce qui appuie sans jamais trancher.

Sortie complète, annotée bloc par bloc : `docs/sortie.md`.

## Pour aller plus loin

- `.claude/README.md` — l'architecture IA, le harnais.
- `src/README.md` — l'architecture du projet, hexagonale à trois couches.
- `profiles/README.md` — les entrées, ce que l'outil lit.
- `docs/sortie.md` — les sorties, ce que l'outil rend.
