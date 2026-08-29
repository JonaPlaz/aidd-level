# Fixture `median-zero-correction-short`

## Ce qu'elle prouve

L'exemple de bordure explicitement donné par la spec (`docs/specs/03-axe-intervention.md` §
Tests) : une médiane 0 sur un échantillon de 8 PR, sous le plancher « jamais »
(`MIN_PR_SAMPLE_ABSENCE = 12`), doit produire la fourchette `[Copper, Silver]` avec un manque
chiffré de 4 PR (12 − 8), et non un Silver confirmé.

## Construction

`pull_requests.median_correction_commits_after_open = 0`, `pull_requests.total = 8`. Les
autres champs (Taille bande L, En parallèle médiane 2) restent au-dessus de leurs propres
planchers (`MIN_PR_SAMPLE = 5`, `PARALLELISM_MIN_PR = 5`) puisque `total = 8 ≥ 5` : seul
Intervention a son propre plancher plus haut (12), donc seul cet axe est en fourchette.

## Niveau attendu

Verdict Intervention : `Range(floor = Copper, ceiling = Silver, missingSample = 4)`. Statut
global `évalué, confiance basse`.
