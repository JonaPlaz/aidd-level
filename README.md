# aidd-level

Évalue le niveau **AI-Driven Development** d'un profil de développeur sur la grille AIDD
(❖ White → 🥇 Gold) et dit ce qui a mené là et comment monter d'un cran.

## Lancer

**Hors du conteneur** :

```
make up
make exec
```

**Dans le conteneur** (après `make exec`) :

```
make evaluate arthur
```

Sortie :

```
❖ 🔺 🔹 🟢 [🥉] 🥈 🥇
axe bloquant : Harness et Intervention (ex æquo)
🥉 Copper — arthur (développeur indépendant)
Niveau atteint : Copper · niveau visé : Silver
...
```

(sortie complète : voir « Exemple de sortie réelle » ci-dessous). Aucune clé d'API, aucun réseau.
`make evaluate` accepte plusieurs noms (`make evaluate arthur bohort`) ; le nom d'un profil est
résolu dans `profiles/`, puis `fixtures/`, sinon pris comme chemin tel quel. Tapée hors du
conteneur, la même commande fonctionne aussi (elle repasse par `docker compose exec`).

**Sans `make`** :

```
docker compose up -d --build
docker compose exec php bin/aidd-level evaluate profiles/arthur
```

**Image autonome** (sans conteneur vivant) :

```
docker build -t aidd-level . && docker run --rm aidd-level evaluate profiles/arthur
```

## Ce que fait l'outil

`aidd-level` lit **un dossier de profil** — les pièces pré-agrégées fournies par le sujet
(`profile.json`, `git-activity.json`, `repo-context/`…), jamais un dépôt git à cloner — et rend
en texte : un niveau de la grille AIDD, l'axe qui l'a plafonné avec chaque fait et son pointeur
(`fichier › champ = valeur`), et le geste le plus actionnable pour monter d'un cran. Aucun appel
LLM à l'exécution, aucune clé d'API, aucune persistance : uniquement du texte, sur la sortie
standard.

La grille en une phrase : sept niveaux (White, Red, Blue, Green, Copper, Silver, Gold), quatre
axes (Taille, Harness, Intervention, En parallèle), un niveau n'est atteint que si **tous** ses
axes le sont — le niveau est donc le **minimum** des quatre verdicts d'axe, jamais une moyenne.
Le détail de la méthode (ce qui est mesuré, pourquoi, les alternatives écartées) est documenté
dans `docs/methode.md`.

## Comment fonctionne le calcul

1. **Un axe par évaluateur.** Chaque axe (`Taille`, `Harness`, `Intervention`, `En parallèle`)
   a son propre évaluateur qui lit `git-activity.json` (et `repo-context/` pour Harness) et
   rend un verdict : le plus haut niveau dont l'axe satisfait la cellule de la grille.
2. **Le minimum décide.** Le niveau final est le minimum des quatre verdicts d'axe — la règle
   du sujet, « un niveau n'est atteint que si tous ses axes le sont ». L'axe (ou les axes ex
   æquo) qui porte ce minimum est **l'axe qui plafonne** : c'est lui, et lui seul, qui apparaît
   en détail dans « Ce qui a mené là ». Les autres axes vont dans « Acquis ».
3. **La médiane décide, jamais le maximum.** Un pic isolé (une PR à 7 branches concurrentes
   quand la médiane est à 4) est cité en note, jamais retenu pour le verdict.
4. **Signal absent.** Quand le champ qui déciderait un axe est absent de `git-activity.json`
   (pas juste un échantillon court), l'axe rend une fourchette ouverte vers White et la
   recommandation devient « fournir le champ … », adressée à qui constitue le dossier, pas au
   développeur.
5. **Trois statuts de sortie.** `évalué` (niveau et axe certains), `évalué, confiance basse`
   (l'échantillon est trop court pour trancher entre deux niveaux, une fourchette est rendue),
   `non évaluable` (un prérequis manque — dossier illisible, `profile.json` ou
   `git-activity.json` absent, ou zéro PR sur la période). Un profil non évaluable n'arrête
   jamais le lot : les autres dossiers passés en argument sont quand même évalués.

## Exemple de sortie réelle

Sortie de `make demo` pour `arthur` (un des quatre profils fournis), copiée telle quelle :

```
❖ 🔺 🔹 🟢 [🥉] 🥈 🥇
axe bloquant : Harness et Intervention (ex æquo)
🥉 Copper — arthur (développeur indépendant)
Niveau atteint : Copper · niveau visé : Silver

Ce qui a mené là — l'axe qui plafonne : Harness et Intervention (ex æquo)
  Harness : context engineering : fichier mémoire présent
    git-activity.json › context_files.agents_md = true
    git-activity.json › context_files = {rules:0, skills:4, hooks:0, agents:2}
    repo-context/.claude/agents/migration-auditor.md › file = present
  Intervention : aux étapes clés
    git-activity.json › pull_requests.median_correction_commits_after_open = 1
    git-activity.json › pull_requests.total = 154

Acquis pour Silver
  Taille : XL → satisfait de Green à Gold
    git-activity.json › pull_requests.median_files_changed = 29
  En parallèle : au moins trois chantiers de front, habituellement
    git-activity.json › parallelism.median_concurrent_branches = 4

Comment monter d'un cran — vers Silver
  1. Harness : ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script,
               sur une commande du projet
  2. Intervention : automatiser la validation (tests, lint, duplication) pour qu'aucune reprise
                    humaine ne soit nécessaire après ouverture

Prochaine quête
  Harness : ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script, sur
            une commande du projet.
  champ à faire bouger : repo-context/ › bounded retry
  preuve actuelle : git-activity.json › context_files.agents_md = true

Notes
  · Taille : échantillon suffisant (plancher 5) : pull_requests.total = 154.
    (git-activity.json › pull_requests.total = 154)
  · Harness : boucles : aucune relance bornée trouvée
    (repo-context/ › bounded retry = none found)
  · Intervention : Gold sur cet axe demanderait la preuve que le cadrage lui-même est automatisé ;
    non observable dans les pièces fournies.
    (git-activity.json › pull_requests.median_correction_commits_after_open = 1)
  · Intervention : merged_without_human_edit_after_open = 46/154 (corrobore, ne décide pas)
    (git-activity.json › pull_requests.merged_without_human_edit_after_open = 46)
  · En parallèle : pic observé : max 7, non retenu
    (git-activity.json › parallelism.max_concurrent_branches = 7)
  · prérequis qualité, cité sans jugement : duplication = 2.4 %
    (sonar-measures.json › duplicated_lines_density = 2.4)
  · prérequis qualité, cité sans jugement : couverture = 85 %
    (sonar-measures.json › coverage = 85)
  · La personne n'a pas répondu au questionnaire déclaratif.
    (profile.json › note = La personne n'a pas répondu au questionnaire déclaratif.)
```

## Structure du dépôt

```
bin/aidd-level          point d'entrée CLI, câblé sur AiddLevel\Infrastructure\Console\ApplicationFactory
src/
  Domain/               calcul pur — Level, Axis, Profile, AxisEvaluator, LevelRule, Assessment…
                         n'importe rien de Application/ ni d'Infrastructure/
  Application/          le use case EvaluateProfile et son handler (gate → axes → LevelRule)
  Infrastructure/        DirectoryProfileSource (lecture du dossier), EvaluateCommand
                         (symfony/console), TextRenderer, ApplicationFactory (câblage à la main)
tests/                  un test par décision de scoring, tests/Calibration/ figeant les quatre
                         niveaux attribués par les organisateurs, tests/expected/ pour le rendu
profiles/               les quatre profils fournis (voir Attribution ci-dessous) et leur README
docs/                   specs/ (les décisions produit), calibration.md, journal.md
```

## Commandes `make`

Tout tourne dans un conteneur de développement vivant (PHP local insuffisant : PHPUnit 13
exige PHP ≥ 8.4.1). `make up` le construit et le lance ; les autres commandes (sauf `up`,
`down` et `evaluate`) échouent avec « Lance d'abord : make up » s'il ne tourne pas.

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
| `make demo` | évalue les quatre profils de `profiles/` puis `profiles/self` |
| `make fmt FILE=…` | formate un fichier PHP |

## Attribution des profils

Les dossiers `profiles/perceval`, `profiles/bohort`, `profiles/leodagan` et `profiles/arthur`
sont copiés tels quels depuis [`ai-driven-dev/laivel-up`](https://github.com/ai-driven-dev/laivel-up)
(commit `89b9e35`, 2026-08-28), sous licence MIT — voir `profiles/ATTRIBUTION.md`.

## En construction

Projet du hackathon [LAIVEL UP](https://github.com/ai-driven-dev/laivel-up) (28–31 août 2026).
Les spécifications sont dans `docs/specs/`, la preuve de calibration dans
`docs/calibration.md`, le plan dans `ROADMAP.md`.

## L'outil sur son propre dépôt

`profiles/self/` est fabriqué depuis l'API GitHub (`python3 scripts/self-profile.py`) ; `docker run --rm aidd-level evaluate profiles/self` rend le verdict du dépôt sur lui-même. Le résultat et sa lecture sont dans [`docs/methode.md`](docs/methode.md) ; le flow réellement suivi dans [`docs/harness.md`](docs/harness.md).
