# Calibration — la preuve que le modèle tombe juste

Quatre profils fournis par le sujet, niveau attribué par les organisateurs, données lues à la
source le 2026-08-28 (`ai-driven-dev/laivel-up`, commit `89b9e35`). Aucun seuil n'a été
ajusté après coup : les bandes et seuils des specs 01–04 ont été posés avant lecture des
données (23–24 août) et validés par elles.

## Données

| Champ | `perceval` | `bohort` | `leodagan` | `arthur` |
|---|---|---|---|---|
| **Niveau attribué** | 🔺 Red | 🔹 Blue | 🟢 Green | 🥉 Copper |
| `pull_requests.total` | 63 | 48 | 71 | 154 |
| `median_files_changed` | **2** | **7** | **13** | **29** |
| `median_lines_changed` | 43 | 251,5 | 579 | 1050 |
| `median_correction_commits_after_open` | **4** | **2** | **0** | **1** |
| `merged_without_human_edit_after_open` | 3 | 10 | 37 | 46 |
| `commits.ai_coauthored_ratio` | 0,04 | 0,58 | 0,87 | 0,91 |
| `parallelism.median_concurrent_branches` | **1** | **1** | **1** | **4** |
| `parallelism.max_concurrent_branches` | 2 | 3 | 2 | 7 |
| `context_files.agents_md` | false | true | true | true |
| rules / skills / hooks / agents | 0/0/0/0 | 0/0/0/0 | 3/3/1/2 | 0/4/0/2 |
| boucle bornée dans `repo-context/` | — (absent) | non | non | non (`docs/brainstorm/2026-06-auto-retry.md` : « Not decided ») |
| Sonar duplication / couverture | 18,4 % / 37 % | 5,8 % / 61 % | 1,7 % / 89 % | 2,4 % / 85 % |

## Verdict par axe (niveau le plus haut dont la cellule est satisfaite)

| Axe | `perceval` | `bohort` | `leodagan` | `arthur` |
|---|---|---|---|---|
| Taille (§ 01) | S → Red | M → Blue | L → Gold | XL → Gold |
| Harness (§ 02) | prompts → Red | context eng. → Blue | behavior → Copper | behavior → Copper |
| Intervention (§ 03) | 4 → Red | 2 → Blue | 0, n=71 ≥ 12 → Silver | 1 → Copper |
| En parallèle (§ 04) | 1 → Green | 1 → Green | 1 → **Green** | 4 → Gold |
| **min()** | **Red** ✅ | **Blue** ✅ | **Green** ✅ | **Copper** ✅ |
| Axe qui plafonne | Taille, Harness, Intervention (ex æquo) | Taille, Harness, Intervention (ex æquo) | En parallèle | Harness, Intervention (ex æquo) |

Quatre sur quatre. L'axe qui plafonne est à chaque fois celui que le dossier met en scène :
`leodagan` est Green **par le seul axe En parallèle** (son déclaratif le dit : « un fil à la
fois »), `arthur` est Copper faute de boucle, `perceval` est Red sur trois axes.

## Ce que le jeu prouve, et ce qu'il ne prouve pas

- **Fichiers avant lignes** : `bohort`, 251,5 lignes (bande L) pour 7 fichiers (bande M),
  est Blue = M. L'ordre inverse la déclasse.
- **Médiane, jamais maximum** : `bohort`, max 3 pour une médiane de 1, est Blue. Le maximum
  la classerait Copper sur cet axe.
- **Le déclaratif ment dans les deux sens** : `perceval` se dit « plutôt avancé » pour un
  ratio de 0,04 ; `bohort` déclare « quelques règles dans un dossier dédié » pour
  `rules_count = 0`. `perceval` dit vrai sur l'absence de fichiers de contexte.
- **Sonar ne changerait rien** : en faire un plafond ne modifie aucun verdict.
- **Non prouvé** : Silver et Gold (aucun profil n'a de boucle ; `leodagan` atteint Silver sur
  Intervention mais est plafonné ailleurs), les planchers d'échantillon (48 à 154 PR, tous
  très au-dessus de 5 et 12), le cas compteurs > 0 sans `agents_md`. Couvert par les fixtures
  maison (`fixtures/`), dont le niveau attendu est fixé par construction, pas par un tiers.

## Reproduction

`make demo` évalue les quatre profils ; `tests/Calibration/` fixe ces quatre verdicts en
tests — un repreneur qui bouge un seuil voit aussitôt ce qui bascule.
