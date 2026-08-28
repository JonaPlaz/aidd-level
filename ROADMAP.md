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
