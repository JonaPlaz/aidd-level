<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

use AiddLevel\Domain\Level;

/**
 * Median `median_correction_commits_after_open` boundaries for the Intervention axis
 * (docs/specs/03-axe-intervention.md § Seuils). Validated against the four supplied profiles
 * (4, 2, 0, 1 → Red, Blue, Silver, Copper).
 */
final class InterventionThresholds
{
    // Red: "after the fact, on the majority" — grid cell, source: docs/specs/00 § 2.
    public const int MEDIAN_MAJORITY_MIN = 3;

    // Blue: "after the fact, on a part" — grid cell.
    public const int MEDIAN_PARTIAL = 2;

    // Copper (Green and Copper share the cell): "at key steps" — grid cell.
    public const int MEDIAN_KEY_STEPS = 1;

    // Silver: "never, once the task is framed" — grid cell; requires SampleFloors::MIN_PR_SAMPLE_ABSENCE.
    public const int MEDIAN_NEVER = 0;

    // The gap, in commits, past which the last pull-requests.json page's commit median is read
    // as a "franche" (flagrant) contradiction of the aggregate median rather than page-to-page
    // noise (docs/specs/03-axe-intervention.md § Corroboration, jamais décision). Assumed
    // adaptation, not sourced — no supplied profile exercises this branch.
    public const int MEDIAN_CONTRADICTION_GAP = 2;

    /**
     * The level for a given median, ignoring sample size: a median of 0 with a sample below
     * SampleFloors::MIN_PR_SAMPLE_ABSENCE must still be turned into a confidence Range by the
     * evaluator (docs/specs/03-axe-intervention.md § Seuils) — this lookup does not see the
     * sample size.
     *
     * An even-sized PR sample can legitimately yield a fractional median (e.g. correction
     * counts 1 and 2 → 1.5): assumed adaptation, not sourced, reading each band as "at least"
     * its threshold — >= 3 majority, >= 2 partial, >= 1 (so 1.5 too) key steps — and reserving
     * "never" for exactly 0; anywhere strictly between 0 and 1 still means some correction
     * happened, so it reads as key steps too, never as "never".
     */
    public static function levelForMedian(float $median): Level
    {
        return match (true) {
            $median >= self::MEDIAN_MAJORITY_MIN => Level::Red,
            $median >= self::MEDIAN_PARTIAL => Level::Blue,
            $median > self::MEDIAN_NEVER => Level::Copper,
            default => Level::Silver,
        };
    }
}
