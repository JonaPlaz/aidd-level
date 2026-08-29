# Fixture `no-repo-context`

## Ce qu'elle prouve

Sans `repo-context/`, les boucles ne sont pas observables — pas absentes, non observables
(`docs/specs/02-axe-harness.md` § Boucles) : l'axe plafonne à Copper avec une note dédiée, qui
diffère de la note « aucune relance bornée trouvée » utilisée quand `repo-context/` est
présent mais ne contient pas de boucle. Cette fixture reprend les signaux réels de `arthur`
(`profiles/arthur/git-activity.json`), qui a lui-même un `repo-context/` sans boucle, pour
isoler la seule différence : ici, `repo-context/` n'existe pas du tout.

## Construction

Mêmes valeurs que `arthur` : `median_files_changed = 29` (XL), `median_correction_commits_
after_open = 1` (Copper), `parallelism.median_concurrent_branches = 4` (Gold),
`context_files.agents_md = true` avec compteurs > 0 (skills, agents) → Harness au moins
« behavior ». Aucun dossier `repo-context/` dans cette fixture.

## Niveau attendu

Harness = **Copper**, avec la note « boucles non observables : repo-context/ absent »
(`docs/specs/02-axe-harness.md` § Boucles). Niveau global **Copper**, plafonné par Harness et
Intervention ex æquo (Intervention : médiane 1 → Copper), exactement comme `arthur` dans
`docs/calibration.md`.
