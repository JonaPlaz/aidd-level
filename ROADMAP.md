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
| 12 | Lancement par conteneur vivant — `make up` / `make exec` hors du conteneur, `make evaluate <nom>` dedans, `make down`, Compose `sleep infinity`, README refait | 00 § 6 (amendée 2026-08-29) | 9, 11 | `compose.yaml`, `Makefile`, `README.md` | #31 | à faire |
| 12 | Lancement par conteneur vivant | 00 § 6 | 9, 11 | — | #31 | **mergé** : #32 `6e0b8a9` — `make up` / `make exec` hors du conteneur, `make evaluate <nom>` dedans ; épreuve du clone neuf réussie par l'agent, rejouée par la session |

**Constaté le 2026-08-30** : upstream publie `venec` et `lancelot` sans niveau (PR #35). Le sujet
(`SUJET.md`, `levels/aidd.md`) n'a pas bougé ; aucune pièce nouvelle ne donne l'auteur par
commit. Deux chantiers de lisibilité et de robustesse en découlent, sans toucher aux seuils.
| 13 | Verdict fragile dit tel quel — note « médiane sur la borne » (Intervention, bornes 3 et 2) ; ratio IA absent jamais coercé en 0 (Harness) | 03 § Médiane sur la borne, 02 § Ratio absent (amendées 2026-08-30) | 12 | `src/Domain/Axis/Intervention/`, `src/Domain/Axis/Harness/`, `tests/` | — | à faire |
| 14 | Détection de boucle resserrée — relance et borne à proximité, fixtures faux positifs (`while` + `budget` éloignés) | 02 § Boucles (à amender) | 13 | `src/Domain/Axis/Harness/Loop*`, `fixtures/`, `tests/` | — | à spécifier |
| 15 | Auto-merge armé par GitHub Actions après le 👍 Codex — cron, chemin « sans remarque » ; fait au plus tôt, avant 13 (arbitré par Jonathan le 2026-08-30) | 08 § Armement par la plateforme (amendée 2026-08-30) | — | `.github/workflows/auto-merge-after-codex.yml` | — | à faire |
| 16 | Cycle imposé par le harnais — `feature` invocable par la session, hook `guard-git` refuse `gh pr create` hors skill et `gh pr merge` synchrone | 08 § Le cycle est imposé (amendée 2026-08-30) | 15 | `.claude/hooks/guard-git.js`, `.claude/skills/feature/SKILL.md`, `CLAUDE.md` | — | à faire |
| 15 | Auto-merge armé par GitHub Actions | 08 | — | — | — | **mergé** : #37 `19e2e83`, correctifs #39 `e3838c8` |
| 16 | Cycle imposé par le harnais | 08 | 15 | — | — | **mergé** : #41 `7c64b11` |
| 13 | Verdict fragile dit tel quel — note « médiane sur la borne » (Intervention, bornes 3 et 2) ; ratio IA absent jamais coercé en 0 (Harness) | 03, 02 | 12 | — | #40 | en cours : lancé par /feature depuis la session (2026-08-30) |
| 13 | Verdict fragile dit tel quel | 03, 02 | 12 | — | #40 | **mergé** : #44 `7e1d1b0` |
| 14 | Détection de boucle resserrée | 02 § Boucles — détection resserrée (2026-08-30) | 13 | `src/Domain/Axis/Harness/Loop*`, `fixtures/`, `tests/` | #45 | spec validée le 2026-08-30, en cours |
| 17 | Lancement autonome de la roadmap — skill `roadmap`, agent `front`, hook `SessionStart` informatif, garde du checkout principal | 08 § 11 (2026-08-30) | 16 | `.claude/skills/roadmap/`, `.claude/agents/front.md`, `.claude/hooks/`, `CLAUDE.md` | #46 | spec validée le 2026-08-30, en cours |
| 17 | Lancement autonome de la roadmap | 08 § 11 | 16 | — | #46 | **mergé** : #51 `b84bcd9` |
| 18 | Mémoire projet — source unique `AGENTS.md`, `CLAUDE.md` en 12 lignes ; arbitrages intégrés à la spec avant commit ; point 9 de revue ; relevé du cron le 2026-08-31 | 08 § 12 (2026-08-30) | 17 | `AGENTS.md`, `CLAUDE.md`, `.claude/skills/feature/`, `.claude/agents/spec.md`, `docs/specs/07`, `docs/journal.md` | #49 | spec validée le 2026-08-30, en cours |
| 14 | Détection de boucle resserrée | 02 § Boucles | 13 | — | #45 | **mergé** : #50 `9b8e0e1` |
| 18 | Mémoire projet | 08 § 12 | 17 | — | #49 | **mergé** : #53 `fddc825` |
| 19 | READMEs d'architecture — panneaux une page : `.claude/README.md` (harnais), `src/README.md` (hexagonal), pointeurs vers specs, zéro duplication (source unique `AGENTS.md`) | 00 § 7 (à amender) | 18 | `.claude/README.md`, `src/README.md` | #56 | à spécifier |
| 19 | Documentation par partie — quatre panneaux, README réduit à trois choses, la doc suit le code | 00 § 7 (2026-08-30) | 18 | `.claude/README.md`, `src/README.md`, `profiles/README.md`, `docs/sortie.md`, `README.md`, `AGENTS.md`, `docs/specs/00-vue-ensemble.md`, `docs/specs/07-amorcage.md`, `docs/specs/08-harnais.md`, `profiles/self/` | #56 | **mergé** : spec #58 `47bfa04`, panneaux #59 `06587c6` |
