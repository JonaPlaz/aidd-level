<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

/**
 * Harness-axis thresholds (docs/specs/02-axe-harness.md § Règle, docs/specs/05-robustesse.md
 * § Filtre White): the only numeric decision this axis makes on
 * `commits.ai_coauthored_ratio` is whether it is exactly zero — "rien" is a real zero ratio
 * with no context-engineering counter at all, never an absent (`null`) ratio treated as zero
 * (docs/specs/02-axe-harness.md § Ratio absent).
 */
final class HarnessThresholds
{
    // Grid wording, source: docs/specs/00-vue-ensemble.md § 2, docs/specs/02 § Règle. A ratio
    // strictly above this is "prompts" (Red); exactly this, with no counter either, is
    // "rien" (White) — the same cutoff the White filter uses (docs/specs/05 § Filtre White).
    public const float AI_RATIO_NONE = 0.0;
}
