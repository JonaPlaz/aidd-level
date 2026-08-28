# 04 — Axe En parallèle

Définition source : « combien de chantiers avancent en même temps, **habituellement**. Un pic
isolé ne compte pas ».

## Signal

`git-activity.json → parallelism.median_concurrent_branches`. **La médiane, jamais le
maximum.** `max_concurrent_branches` sert une seule chose : la note « pic observé, non
retenu ».

Piège central du jeu d'épreuve : `bohort` a un maximum de **3** — la valeur exacte que Copper
demande — pour une médiane de **1**, et elle est Blue. Lire le maximum la déclasse.

Le calcul par jour calendaire prévu avant le 28, avec `MIN_JOURS_ECHANTILLON` et
`SEUIL_HABITUEL_PCT`, est **caduc** : la médiane est fournie, la reconstruire n'aurait ni
meilleure précision ni source.

## Seuils (`ParallelismThresholds`)

| Médiane | Niveau |
|---|---|
| 0 | White (avec le filtre d'entrée, § 05) |
| 1–2 | Green (satisfait « 1 » de Red à Green ; « 3 » de Copper non atteint) |
| ≥ 3 | Gold (« 3 » est un minimum, satisfait de Copper à Gold) |

Validés sur les quatre profils (1, 1, 1, 4 → Green, Green, Green, Gold).

## Confiance

`pull_requests.total < SampleFloors::PARALLELISM_MIN_PR` (5) → fourchette [niveau de la
médiane, Gold], manque chiffré. Constante non sourcée, adaptation assumée.

Signal absent (`null`) : règle commune de la spec 05 § *Signal absent* — `Range(White, plafond
de l'axe, 0)` et une note par champ manquant.

## Preuves rendues

- `git-activity.json › parallelism.median_concurrent_branches = 1 → un chantier à la fois`
- note : `parallelism.max_concurrent_branches = 3 — pic observé, non retenu`

## Actionnabilité

Actionnable, **conditionné par le harness** : ouvrir plusieurs fronts sans dispositif produit
des conflits, pas du niveau. Recommandé en second, jamais avant Harness. Geste type :
« isoler chaque chantier (worktree ou équivalent) et livrer deux fronts en même temps sur une
semaine ». Voir § 06.

## Tests

Quatre profils → valeurs ci-dessus · fixture médiane 3 → Gold · fixture médiane 1, max 6 →
Green + note pic · fixture `total = 2` → fourchette.
