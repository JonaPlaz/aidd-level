# ROADMAP

Plan ordonné, dérivé des spécifications de `docs/specs/`. Fichier **append-only** : une ligne
par chantier, l'état se met à jour en ajoutant une ligne, jamais en réécrivant.

Deux chantiers sont simultanés quand leurs specs ne partagent aucun fichier de sortie et
qu'aucun ne dépend du résultat de l'autre. Le nombre de fronts ouverts se déduit de la colonne
« dépend de », il ne se décrète pas.

| # | Chantier | Spec | Dépend de | Sorties | Issue | État |
|---|---|---|---|---|---|---|
| 0 | Harnais — agents, skills, hooks, CI, journal | 07, 08 | — | `.claude/`, `.github/`, `ROADMAP.md` | — | en cours (branche `chore/harness`) |
| 1 | Noyau de domaine — niveaux, axes, preuves, verdicts, règle du minimum, port d'entrée, constantes de seuils | 00 § 4.1 | 0 | `src/Domain/` (hors `Axis/*`), `tests/Domain/` | — | à faire |
| 2 | Évaluateur Taille | 01 | 1 | `src/Domain/Axis/Size/`, `tests/Domain/Axis/Size/` | — | à faire |
| 3 | Évaluateur Harness | 02 | 1 | `src/Domain/Axis/Harness/`, `tests/Domain/Axis/Harness/` | — | à faire |
| 4 | Évaluateur Intervention | 03 | 1 | `src/Domain/Axis/Intervention/`, `tests/Domain/Axis/Intervention/` | — | à faire |
| 5 | Évaluateur En parallèle | 04 | 1 | `src/Domain/Axis/Parallelism/`, `tests/Domain/Axis/Parallelism/` | — | à faire |
| 6 | Adaptateur dossier de profil — lecteurs JSON/Markdown, gate, cohérence `available` | 05, 00 § 4.3 | 1 | `src/Infrastructure/Profile/`, `tests/Infrastructure/Profile/` | — | à faire |
| 7 | Rendu texte et table des gestes — trois blocs, notes, barre de niveaux, prochaine quête | 06 | 1 | `src/Domain/Progression/`, `src/Infrastructure/Render/`, `tests/…/Render/` | — | à faire |
| 8 | Use case et robustesse — handler, filtre White, statuts, fourchettes | 05, 00 § 4.2 | 2, 3, 4, 5, 6 | `src/Application/`, `tests/Application/` | — | à faire |
| 9 | Commande console, `bin/aidd-level`, `make demo`, tests de calibration, README détaillé | 00 § 6, calibration | 7, 8 | `src/Infrastructure/Console/`, `bin/`, `tests/Calibration/`, `README.md` | — | à faire |
| 10 | Fixtures maison — Silver, Gold, White, planchers d'échantillon, compteurs sans mémoire | 02–05 « non prouvé » | 9 | `fixtures/`, `tests/Fixtures/` | — | à faire |
| 11 | Profil `self`, `docs/methode.md`, `docs/harness.md`, passe de refactor, vidéo | 00 § 7–8, 08 § 10 | 9, 10 | `profiles/self/`, `docs/`, `README.md` | — | à faire |

**Parallélisme calculé** : après le chantier 1, les chantiers 2, 3, 4, 5, 6 et 7 n'ont aucune
sortie commune et ne dépendent que de 1 → **six fronts ouvrables**. La spec du harnais prévoit
de commencer à trois (doute 6 du brief : trois worktrees simultanés jamais éprouvés). 8 attend
2–6 ; 9 attend 7 et 8 ; 10 et 11 ferment.

| 0 | Harnais | 07, 08 | — | — | — | **mergé** : #13 `5e9a65c`, corrections #14 `27feb20`, #16 `1cedbfe` (règles de boucle révisées deux fois, cf. journal) |
| 1 | Noyau de domaine | 00 § 4.1 | 0 | — | #2 | **mergé** : #15 `30062a0` (3 passes Codex) |
| 2 | Évaluateur Taille | 01 | 1 | — | #3 | **mergé** : #17 `30ec31a` |
| 3 | Évaluateur Harness | 02 | 1 | — | #4 | **mergé** : #19 `2a83301` (borne `maxTurns` atteinte une fois) |
| 4 | Évaluateur Intervention | 03 | 1 | — | #5 | **mergé** : #18 `d51c8e9` |
| 5 | Évaluateur En parallèle | 04 | 1 | — | #6 | **mergé** : #20 `a48066b` |
| 6 | Adaptateur dossier de profil | 05 | 1 | — | #7 | **mergé** : #21 `78f1873` |
| 7 | Rendu texte et gestes | 06 | 1 | — | #8 | en revue : #22 |

**Constaté le 2026-08-29** : six fronts ouverts simultanément après le chantier 1 (2, 3, 4, 5,
6, 7), comme le graphe le prévoyait ; l'intégration s'est faite en cascade séquentielle
(check `ci` requis en mode `strict` : chaque merge remet les autres en retard, rebase à
chaque fois).
| 8 | Use case et robustesse | 05 | 2–6 | — | #9 | **mergé** : #24 `c81173a` — les quatre profils réels tombent juste de bout en bout |
| 9 | Console, demo, calibration, README | 00 § 6 | 7, 8 | — | #10 | **mergé** : #25 `dd06334` — épreuve du clone propre réussie |
| 10 | Fixtures maison | 02–05 | 9 | — | #11 | **mergé** : #26 `53acee1` — 10 fixtures, 👍 Codex sans remarque |
| 11 | Profil self, méthode, harness.md | 00 § 7–8 | 9, 10 | — | #12 | en revue — reste : passe de refactor, vidéo (Jonathan) |
| 11 | Profil self, méthode, harness.md, refactor | 00 § 7–8 | 9, 10 | — | #12 | **mergé** : #27 `2eee192` (self, méthode, harness.md), #28 `4da3e0f` (refactor, −128/+65 lignes, tests inchangés) — reste la vidéo (Jonathan) |
| 12 | Lancement par conteneur vivant — `make up` / `make exec` / `make down`, `bin/aidd-level evaluate` dans le conteneur, Compose `sleep infinity`, README refait | 00 § 6 (amendée 2026-08-29) | 9, 11 | `compose.yaml`, `Makefile`, `README.md` | #31 | à faire |
