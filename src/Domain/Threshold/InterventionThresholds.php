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

    /**
     * The level for a given median, ignoring sample size: a median of 0 with a sample below
     * SampleFloors::MIN_PR_SAMPLE_ABSENCE must still be turned into a confidence Range by the
     * evaluator (docs/specs/03-axe-intervention.md § Seuils) — this lookup does not see the
     * sample size.
     */
    public static function levelForMedian(int $median): Level
    {
        return match (true) {
            $median >= self::MEDIAN_MAJORITY_MIN => Level::Red,
            self::MEDIAN_PARTIAL === $median => Level::Blue,
            self::MEDIAN_KEY_STEPS === $median => Level::Copper,
            default => Level::Silver,
        };
    }
}
