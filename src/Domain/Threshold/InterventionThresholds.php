<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

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
}
