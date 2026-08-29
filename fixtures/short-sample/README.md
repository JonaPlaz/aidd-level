# Fixture `short-sample`

## Ce qu'elle prouve

Le comportement en confiance basse n'est validé par aucun des quatre profils fournis : ils ont
tous 48 à 154 PR, très au-dessus des planchers (`docs/specs/05-robustesse.md` § Planchers
d'échantillon). Cette fixture reprend exactement les valeurs de `leodagan`
(`profiles/leodagan/git-activity.json`) mais ramène `pull_requests.total` à 3 : sous chaque
plancher (`MIN_PR_SAMPLE = 5`, `MIN_PR_SAMPLE_ABSENCE = 12`, `PARALLELISM_MIN_PR = 5`), un
échantillon minuscule doit produire des fourchettes chiffrées plutôt qu'un verdict confirmé.

## Construction

- Mêmes signaux que `leodagan` : `median_files_changed = 13`, `median_correction_commits_after_
  open = 0`, `parallelism.median_concurrent_branches = 1`, `context_files.agents_md = true`
  avec compteurs > 0.
- `pull_requests.total = 3` (au lieu de 71 chez `leodagan`).
- Pas de `repo-context/` : l'axe Harness n'a pas de plancher d'échantillon (c'est une preuve,
  pas un volume), il reste confirmé à Copper avec une note « boucles non observables ».

## Niveau attendu

**`évalué, confiance basse`** :

- Taille : bande L (Gold) mais échantillon insuffisant → `Range(Gold, Gold, manque = 2)`.
- Harness : Copper, confirmé (aucun plancher d'échantillon sur cet axe).
- Intervention : médiane 0, `total = 3 < 12` → `Range(Copper, Silver, manque = 9)`.
- En parallèle : médiane 1 (Green), `total = 3 < 5` → `Range(Green, Gold, manque = 2)`.
- Plancher global = Green (le plus bas), axe qui plafonne le plancher : En parallèle.
