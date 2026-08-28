<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

/**
 * Regex motifs used by LoopDetector to spot a bounded retry loop under `repo-context/`
 * (docs/specs/02-axe-harness.md § Boucles). Not verified on real data: none of the four
 * calibration profiles has a loop; only the maison fixtures exercise these patterns.
 */
final class LoopPatterns
{
    /** A restart of some kind: retry, until, while, attempt, rerun. */
    public const string RETRY = '/retry|until|while|attempt|rerun/i';

    /** A visible cap: a named budget constant, or a comparison against an integer. */
    public const string BOUND = '/max_attempts|max-retries|max_retries|MAX_ITER|budget|attempts?\s*[<>=]+\s*\d+/i';
}
