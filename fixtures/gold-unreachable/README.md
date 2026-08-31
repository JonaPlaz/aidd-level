# Fixture `gold-unreachable`

## Ce qu'elle prouve

`docs/specs/03-axe-intervention.md` § Gold dit que l'axe Intervention **plafonne à Silver par
construction** : aucune pièce fournie ne distingue un cadrage humain d'un cadrage automatisé,
donc Gold y est structurellement inatteignable, quelles que soient les valeurs. Cette fixture
pousse les trois autres axes (Taille, Harness, En parallèle) à Gold pour isoler ce plafond :
même dans le meilleur des cas ailleurs, le niveau global ne dépasse jamais Silver tant que
l'échantillon d'Intervention est « jamais » (médiane 0).

## Construction

- `pull_requests.median_files_changed = 25` → bande XL → Gold sur Taille.
- `parallelism.median_concurrent_branches = 5` → Gold sur En parallèle.
- `context_files.agents_md = true` + compteurs > 0 + boucle bornée détectée dans
  `repo-context/.github/workflows/ci.yml` (`retry` + `max_attempts: 3`) → Gold sur Harness.
- `pull_requests.median_correction_commits_after_open = 0`, `total = 40 ≥ 12` → Silver sur
  Intervention, **plafond de l'axe** : la sortie le dit sur sa ligne *Niveau suivant*
  (« hors d'atteinte ici : l'axe Intervention plafonne à Silver, « cadrage compris » ne se
  constate dans aucune pièce fournie », docs/specs/06-sortie-et-progression.md § 5.1) — la
  preuve que Gold n'est pas juste non atteint ici, il est hors de portée par construction.

## Niveau attendu

**Silver**, plafonné par le seul axe Intervention (Taille, Harness et En parallèle sont à
Gold). La sortie doit porter la note de plafond d'Intervention.
