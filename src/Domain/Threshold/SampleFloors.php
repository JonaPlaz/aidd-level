<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

/**
 * Minimum pull-request sample sizes below which a verdict becomes a confidence Range
 * (docs/specs/05-robustesse.md § Planchers d'échantillon). None of these is sourced by the
 * brief or the grid: assumed adaptation, covered by dedicated fixtures rather than by the
 * four supplied profiles (which all have 48 to 154 pull requests). The unit is always the
 * pull request, never the commit.
 */
final class SampleFloors
{
    // assumed adaptation, not sourced — Size; Intervention except "never" (Silver).
    public const int MIN_PR_SAMPLE = 5;

    // assumed adaptation, not sourced — Intervention "never" (Silver): the only claim of
    // absence, so a single counter-example refutes it and needs a larger sample.
    public const int MIN_PR_SAMPLE_ABSENCE = 12;

    // assumed adaptation, not sourced — Parallelism.
    public const int PARALLELISM_MIN_PR = 5;

    // docs/specs/05-robustesse.md § Gate, step 4: at least one pull request or nothing can be
    // measured — the gate cutoff, not a confidence floor, but named here alongside the other
    // pull-request sample sizes.
    public const int GATE_MIN_PR = 1;
}
