<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

use AiddLevel\Domain\Level;

/**
 * Median `parallelism.median_concurrent_branches` boundaries for the Parallelism axis
 * (docs/specs/04-axe-parallele.md § Seuils). The maximum is never read here — a peak is
 * reported as a note, not retained. Validated against the four supplied profiles
 * (1, 1, 1, 4 → Green, Green, Green, Gold).
 */
final class ParallelismThresholds
{
    // White: no concurrent branch at all — grid cell, source: docs/specs/00 § 2.
    public const int MEDIAN_NONE = 0;

    // Green: satisfies the "1" cell from Red to Green; "3" (Copper) is not reached.
    public const int MEDIAN_SOME_MIN = 1;
    public const int MEDIAN_SOME_MAX = 2;

    // Gold: "3" is a minimum, satisfied from Copper to Gold (§ 2, "chaque cellule est un minimum").
    public const int MEDIAN_HABITUAL_MIN = 3;

    /**
     * The level for a given median, ignoring sample size (docs/specs/04-axe-parallele.md §
     * Seuils/Confiance) — a sample below SampleFloors::PARALLELISM_MIN_PR must still be
     * turned into a confidence Range by the evaluator.
     *
     * An even-sized PR sample can legitimately yield a fractional median (e.g. concurrent
     * branch counts 0 and 1 → 0.5): assumed adaptation, not sourced, reading "White" as
     * exactly 0 and anything strictly above it — including 0 < x < 1 — as at least "some"
     * concurrency (Green), since a fraction above zero still means more than one chantier
     * touched the sample.
     */
    public static function levelForMedian(float $median): Level
    {
        return match (true) {
            $median >= self::MEDIAN_HABITUAL_MIN => Level::Gold,
            $median > self::MEDIAN_NONE => Level::Green,
            default => Level::White,
        };
    }
}
