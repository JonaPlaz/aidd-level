<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

/**
 * Thresholds that parametrize the loop *detection* itself (docs/specs/02-axe-harness.md
 * § « Boucles — détection resserrée », chantier 14) — never a threshold the grid uses to
 * decide a level. That is why these two constants live here, next to `LoopPatterns` that
 * they serve, and not in `src/Domain/Threshold/` where `HarnessThresholds` keeps
 * `AI_RATIO_NONE`, the only constant that actually decides a Harness value.
 */
final class LoopThresholds
{
    /**
     * A restart token and a bound token only count as a loop when they sit within this many
     * lines of each other (order indifferent). Adaptation assumée, calibrée sur trois formes
     * réelles vérifiées le 2026-08-30 (§ 1) : une étape GitHub Actions `nick-fields/retry`
     * paramétrée en entier (`max_attempts` jusqu'à ~8 lignes après `uses:`), une relance shell
     * à compteur (`n=0` / `until` / `[ "$n" -ge 3 ]`, deux à six lignes), GitLab CI (`retry:`
     * puis `max:` sur la ligne suivante). 10 laisse la marge de ces trois formes et ne laisse
     * pas passer le cas de l'issue #45 (deux tokens à 200 lignes d'écart). Borne de sécurité,
     * pas une mesure : aucune campagne sur un corpus réel ne l'a établie (§ 8).
     */
    public const int PROXIMITY_LINES = 10;

    /**
     * The longest counted loop (`for … in $(seq …)` / `{1..N}`, restricted to a literal start
     * of 1 and a literal step of 1 so the length is knowable without executing anything, § 4)
     * that still reads as a bounded retry rather than a batch job. Reprise telle quelle du
     * § « Boucles » d'origine (« entier ≤ 20 associé ») — jamais sourcée, seulement déplacée
     * là où elle s'applique (§ 8).
     */
    public const int COUNTED_MAX = 20;
}
