<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

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
}
