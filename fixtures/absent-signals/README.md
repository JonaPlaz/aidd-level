# Fixture `absent-signals`

## Ce qu'elle prouve

La règle « Signal absent » (`docs/specs/05-robustesse.md` § Signal absent — distinct de
l'échantillon court) : quand le champ qui décide un axe est absent de `git-activity.json`, ce
n'est pas un échantillon insuffisant (le manque n'est pas un nombre de PR), c'est un champ à
fournir. Aucun des quatre profils fournis n'a de champ décideur manquant.

## Construction

`git-activity.json › pull_requests` et `parallelism` n'ont **aucun** des quatre champs
suivants :

- `median_files_changed`
- `median_lines_changed`
- `median_correction_commits_after_open`
- `parallelism.median_concurrent_branches`

`context_files.agents_md` reste renseigné (`true`) : seuls les quatre champs ci-dessus sont
testés comme absents, pas Harness.

## Niveau attendu

**`évalué, confiance basse`** :

- Taille : les deux champs (fichiers et lignes) absents → `Range(White, Gold, manque = 0)`,
  deux notes pointées « absent ».
- Intervention : médiane absente → `Range(White, Silver, manque = 0)`, note pointée « absent ».
- En parallèle : médiane absente → `Range(White, Gold, manque = 0)`, note pointée « absent ».
- Harness : normal (`agents_md = true`, compteurs présents), non concerné.
- Niveau global plafonné à White par les trois axes signal-absent ; les recommandations en
  tête sont toutes des « fournir le champ … », dans l'ordre `RecommendationPolicy::AXIS_ORDER`
  (En parallèle, puis Intervention, puis Taille).
