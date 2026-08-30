# Fixture `loop-far-apart`

## Ce qu'elle prouve

Le constat de l'issue #45 : `LoopDetector` promouvait à Gold tout fichier éligible portant un
motif de relance **quelque part** et un motif de borne **ailleurs**, même à 200 lignes
d'écart. Cette fixture est construite pour reproduire ce cas exact et prouver que le
resserrement (docs/specs/02-axe-harness.md § « Boucles — détection resserrée », chantier 14)
le referme : sans la fenêtre de proximité, Harness serait Gold ici (comme `silver-loop`) ;
avec elle, Harness plafonne à Copper, et c'est le seul axe qui plafonne le niveau global.

## Construction

- Mêmes signaux que `fixtures/silver-loop/git-activity.json` : `context_files.agents_md =
  true`, `rules_count`, `skills_count`, `hooks_count` > 0 → Harness au moins « behavior » ;
  `pull_requests.median_files_changed = 13` (bande L) → Gold sur Taille ;
  `parallelism.median_concurrent_branches = 3` → Gold sur En parallèle ;
  `pull_requests.median_correction_commits_after_open = 0` avec `total = 20 ≥
  SampleFloors::MIN_PR_SAMPLE_ABSENCE (12)` → Intervention = Silver, confirmé.
- `repo-context/scripts/deploy.sh` (surface éligible : `scripts/` + `.sh`, § 3) contient
  `while` en ligne 3 et `budget` en ligne 210, à 207 lignes d'écart — largement au-delà de
  `LoopThresholds::PROXIMITY_LINES` (10). Rien d'autre dans le fichier : aucun autre motif de
  relance ni de borne.
- `while` ne **nomme** pas une relance (`retry`, `rerun`, `until` seuls le font,
  docs/specs/02-axe-harness.md § 5) : la note « relance non bornée trouvée » ne se déclenche
  donc **pas** ici, seule la note « boucles : aucune relance bornée trouvée » est rendue.

## Niveau attendu

Harness = **Copper** (aucune boucle bornée détectée, note « boucles : aucune relance bornée
trouvée » seule). Niveau global **Copper**, plafonné par **Harness seul** — Taille, Intervention
(Silver) et En parallèle restent tous à Silver ou au-dessus, donc jamais capping.
