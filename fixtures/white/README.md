# Fixture `white`

## Ce qu'elle prouve

Le filtre White (`docs/specs/05-robustesse.md` § Filtre White) : `commits.ai_coauthored_ratio
= 0` **et** aucun compteur de `context_files` (tous à 0, `agents_md = false`) → White sur les
quatre axes, sans même appeler un `AxisEvaluator`. Aucun des quatre profils fournis n'est dans
ce cas (`perceval`, le plus bas, a un ratio de 0,04).

## Construction

- `commits.ai_coauthored_ratio = 0.0` (un vrai zéro, pas une valeur absente).
- `context_files.agents_md = false`, `rules_count = skills_count = hooks_count = agents_count
  = 0`.
- Les autres champs (`median_files_changed`, `median_correction_commits_after_open`,
  `parallelism.median_concurrent_branches`) portent des valeurs non nulles : ils sont sans
  effet, le filtre court-circuite tout calcul par axe.

## Niveau attendu

**White** sur les quatre axes à la fois (Taille, Harness, Intervention, En parallèle), niveau
global White, statut « évalué ».
