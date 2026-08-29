# 00 — Vue d'ensemble

> Spécification produit. Source unique : le brief du hackathon (`.brief/hackathon-laivel-up.md`,
> jamais versionné). Ce qui porte un chiffre ou un nom a été revérifié à la source le 2026-08-28
> (dépôt `ai-driven-dev/laivel-up`, commit `89b9e35`). Ce qui n'a pas pu l'être est marqué
> **non vérifié**.

## 1. Ce que fait l'outil

Un CLI qui lit **un dossier de profil** (les pièces fournies par le sujet) et rend, en texte :

1. **un niveau** de la grille AIDD (White → Gold) ;
2. **ce qui a mené là** — l'axe qui plafonne, avec chaque fait et l'endroit où il se constate ;
3. **comment monter d'un cran** — un geste, ordonné par actionnabilité.

Aucun appel LLM à l'exécution, aucune clé d'API, aucune persistance, sortie texte seule.
Ces quatre points sont tranchés (brief, § *Ce qui est déjà tranché*) et ne se rouvrent pas.

## 2. La grille appliquée

`levels/aidd.md`, sept niveaux, quatre axes, revérifiée ligne à ligne le 2026-08-28 :

| Niveau | rang | Taille | Harness | Intervention | En parallèle |
|---|---|---|---|---|---|
| ❖ White | 0 | aucune | rien | — | 0 |
| 🔺 Red | 1 | S | prompts | après coup, sur la majorité | 1 |
| 🔹 Blue | 2 | M | context engineering | après coup, sur une partie | 1 |
| 🟢 Green | 3 | L | context engineering, behavior | aux étapes clés | 1 |
| 🥉 Copper | 4 | L-XL | context engineering, behavior | aux étapes clés | 3 |
| 🥈 Silver | 5 | L-XL | + boucles | jamais, une fois la tâche cadrée | 3 |
| 🥇 Gold | 6 | L-XL | + boucles | jamais, cadrage compris | 3 |

Trois règles de la source, appliquées telles quelles :

- « Un niveau n'est atteint que si **tous ses axes** le sont » → **le niveau est le minimum
  des quatre axes.**
- « Chaque cellule est un **minimum**, pas une valeur exacte » → un axe vaut **le plus haut
  niveau dont il satisfait la cellule** ; 4 chantiers satisfont « 3 », L satisfait « L-XL ».
- « La colonne "Ce qu'on observe" illustre, elle ne décide pas. »

Hors périmètre (source) : séniorité, qualité du code (prérequis, pas axe), volume d'usage.

## 3. Les données d'entrée

Un profil est **un dossier de mesures pré-agrégées**, pas un dépôt git. Huit pièces possibles,
jamais toutes présentes (`profiles/README.md`) :

| Pièce | Rôle dans le calcul |
|---|---|
| `profile.json` | identité ; tableau `available` = cohérence annoncé / présent |
| `git-activity.json` | **colonne vertébrale : les quatre axes se résolvent depuis lui** (36 champs, schéma identique sur les quatre profils fournis) |
| `repo-context/` | cite la preuve de l'axe Harness ; détection des boucles (§ 02) |
| `sonar-measures.json` | note de prérequis qualité, hors calcul |
| `declaratif.md` | hors calcul ; mesure l'écart dit / constaté, sert le plan de progression |
| `pull-requests.json` | inventorié (cohérence `available`), **hors calcul** : ses champs ne se comparent à aucune médiane (spec 03, 2026-08-29) |
| `code/` · `session.md` | non lus par le calcul |

Trois avertissements de la source à respecter : les autres profils n'auront pas les mêmes
trous ; les outils diffèrent (« ce qui compte, c'est ce qui est en place, jamais la marque » —
**détecter `.claude/` serait une faute**) ; ne pas croire le déclaratif, ne pas s'arrêter aux
métriques, ne pas confondre richesse et niveau.

## 4. Architecture — hexagonale, trois couches, un seul domaine

Stack : **PHP 8.5**, `symfony/console ^7.4` (LTS), PHPUnit 13, Composer 2.10, image
`php:8.5-cli-alpine`. Versions revérifiées le 2026-08-28 (Packagist, endoflife.date).
⚠️ PHP local est 8.3.6 : **tout l'outillage tourne dans Docker**, y compris pour le
développement (PHPUnit 13 exige ≥ 8.4.1).

```
src/
  Domain/         calcul pur — aucune dépendance vers les deux autres couches
  Application/    le use case EvaluateProfile et son handler
  Infrastructure/ adaptateur dossier, lecteurs de pièces, commande console, rendu texte
```

Namespace racine : `AiddLevel\`. Code, identifiants et commentaires en anglais ; README et
docs en français ; **et tout texte destiné à l'utilisateur en français** — claims des
`Evidence`, textes des `Note`, gestes des `Recommendation`, messages de `ProfileNotAssessable`,
rendu : c'est la sortie que lit le jury, et la spec 06 en fixe le vocabulaire (« après coup,
sur la majorité », « aux étapes clés »…). Les identifiants de pointeurs (`git-activity.json ›
pull_requests.total`) restent tels quels. Précisé le 2026-08-29 (remarque Codex sur la PR
#18).

### 4.1 Domaine — les types

| Type | Rôle |
|---|---|
| `Level` (enum) | les sept niveaux avec `rank()` et `label()` (icône + nom) |
| `Axis` (enum) | `Size`, `Harness`, `Intervention`, `Parallelism` |
| `Profile` | l'agrégat lu : identité, `GitActivity`, pièces optionnelles (`RepoContext`, `SonarMeasures`, `Declarative`, `PullRequests`) — chacune nullable |
| `Evidence` | une affirmation **et son pointeur** (fichier, champ, valeur). Une preuve sans pointeur ne se construit pas (règle 4 de § 06) |
| `AxisVerdict` | axe, niveau atteint, confiance (`Confirmed` / `Range(floor, ceiling, missing)`), preuves |
| `AxisEvaluator` (interface) | **le point d'extension** : `evaluate(Profile): AxisVerdict`. Un évaluateur par axe. Ajouter un axe = une classe, le calcul du minimum ne change pas |
| `LevelRule` | le minimum des verdicts → `Assessment` ; identifie l'axe (ou les axes ex æquo) qui plafonne |
| `Assessment` | statut (`Evaluated` / `LowConfidence` / `NotAssessable`), niveau, axes plafonnants, verdicts, `Recommendation`, notes |
| `Recommendation` | le geste vers le cran suivant, ordonné selon § 06 |
| `ProfileSource` (port) | `load(string $path): Profile` — la seule frontière d'entrée |
| `*Thresholds` (constantes) | `SizeThresholds`, `InterventionThresholds`, `ParallelismThresholds`, `SampleFloors` — chaque constante porte en commentaire son origine ou « adaptation assumée » |

### 4.2 Application

`EvaluateProfile` (requête : chemin) → `EvaluateProfileHandler` : gate (§ 05) →
évaluateurs → `LevelRule` → `Assessment`. Aucune exception ne remonte à l'utilisateur :
un échec de gate devient un `Assessment` de statut `NotAssessable`.

### 4.3 Infrastructure

`DirectoryProfileSource` (implémente le port, lit JSON et Markdown), `EvaluateCommand`
(`symfony/console`), `TextRenderer` (§ 06). Un format d'entrée inattendu coûte un adaptateur,
rien d'autre ne bouge.

### 4.4 Ce qui est écarté, et pourquoi

Pas de persistance, pas de séparation commande/query, pas d'interface par entité, pas de port
LLM, pas d'adaptateur git : chacun n'aurait aucune seconde implémentation, et une abstraction
vide se paie sur le critère « la qualité est là ? ».

## 5. Règles non négociables

1. `Domain/` n'importe jamais `Application/` ni `Infrastructure/` (gardé par hook, § 08).
2. Tout seuil vit dans une constante nommée avec sa justification à côté.
3. Toute ligne d'explication porte un pointeur vérifiable.
4. Le déclaratif n'entre dans aucun calcul.
5. La médiane décide, jamais le maximum (« un pic isolé ne compte pas »).
6. Un profil incomplet ne fait jamais planter : il sort un statut.
7. Un commit ne touche pas domaine et infrastructure ensemble (gardé par hook).

## 6. Interface utilisateur

```
bin/aidd-level evaluate <dossier-profil> [<dossier-profil>...]
```

Un profil qui échoue au gate n'arrête pas l'évaluation des autres. Sans argument, la commande
liste les profils livrés dans `profiles/`.

**Lancement de référence — arbitré par Jonathan le 2026-08-29** (remplace `docker build` +
`docker run`, jugé moins lisible qu'un conteneur qui tourne) : un conteneur de développement
qui reste vivant, piloté par `make` :

| Commande | Derrière |
|---|---|
| `make up` | `docker compose up -d --build` — construit l'image, installe les dépendances, laisse le service `php` tourner (`command: sleep infinity`) |
| `make demo` | les quatre profils fournis puis `profiles/self` |
| `make exec` | `docker compose exec php sh` — **on est dans le conteneur** ; l'outil s'y lance directement : `bin/aidd-level evaluate profiles/arthur` |
| `make down` | `docker compose down` |
| `make test` · `lint` · `dup` · `fmt` | `docker compose exec php …` (le conteneur doit tourner ; message clair sinon) |

Notice du README, trois lignes : `make up`, `make exec`, puis dans le conteneur
`bin/aidd-level evaluate profiles/arthur`. Pas de cible `make evaluate` : l'exec est déjà
fait, la commande de l'outil se tape telle quelle. Repli sans `make`, dans
le README sous la notice : les deux commandes `docker compose` équivalentes, et l'image
autonome `docker build -t aidd-level . && docker run --rm aidd-level evaluate profiles/arthur`
(le `Dockerfile` garde son `ENTRYPOINT`).

Les quatre profils fournis sont recopiés dans `profiles/` avec attribution MIT
(`profiles/ATTRIBUTION.md` → `ai-driven-dev/laivel-up`, commit `89b9e35`).

## 7. Livrables notés, hors code

`README.md` (notice en trois lignes puis détail), `docs/methode.md` (une page : ce qu'on
mesure, pourquoi, le flow suivi ; cite le framework AIDD examiné et écarté, les conventions
GenAI OpenTelemetry connues et écartées, et le seul geste manuel — l'activation de la revue
Codex), `docs/calibration.md`, `docs/harness.md`, `ROADMAP.md`, `docs/journal.md`, une vidéo
ou GIF de deux minutes, muette.

## 8. Cohérence obligatoire

L'outil tourne sur son propre dépôt : un profil `profiles/self/` est fabriqué depuis le dépôt
rendu (compteurs de contexte, médianes des PR réelles, branches concurrentes constatées). Le
résultat figure dans la vidéo. Un dépôt qui se noterait Red se disqualifie seul.
