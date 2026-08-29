# Fixture `silver-loop`

## Ce qu'elle prouve

Aucun des quatre profils fournis n'a de boucle de relance bornée, et aucun n'atteint Silver
sur Intervention (`docs/calibration.md` § « Ce que le jeu prouve, et ce qu'il ne prouve pas » ;
`docs/specs/02-axe-harness.md` § Boucles ; `docs/specs/03-axe-intervention.md` § Seuils). Cette
fixture construit les deux à la fois : une boucle de relance bornée réellement détectable dans
`repo-context/`, et une médiane de correction à 0 sur un échantillon suffisant (`total = 20 ≥
12`).

## Construction

- `git-activity.json › context_files.agents_md = true`, compteurs `rules_count`,
  `skills_count`, `hooks_count` > 0 → Harness au moins « behavior ».
- `repo-context/.github/workflows/ci.yml` contient `retry` (motif de relance) **et**
  `max_attempts: 3` (motif de borne), deux tokens indépendants dans le même fichier éligible
  (`.github/workflows/`) → Harness détecte une boucle → Gold sur cet axe.
- `pull_requests.median_correction_commits_after_open = 0` avec `total = 20 ≥
  SampleFloors::MIN_PR_SAMPLE_ABSENCE (12)` → Intervention = Silver, confirmé.
- `pull_requests.median_files_changed = 13` (bande L) → Gold sur Taille.
- `parallelism.median_concurrent_branches = 3` → Gold sur En parallèle.

## Niveau attendu

**Silver**, plafonné par l'axe Intervention seul (plafond Silver par construction — voir la
note « Gold sur cet axe demanderait la preuve que le cadrage lui-même est automatisé »). Les
trois autres axes (Taille, Harness, En parallèle) atteignent Gold : Harness et Intervention se
trouvent donc chacun en haut de leur propre plafond (Gold pour Harness, Silver pour
Intervention), mais seul Intervention plafonne le niveau global.
