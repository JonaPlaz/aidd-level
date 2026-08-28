<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

/**
 * Regex motifs used by LoopDetector to spot a bounded retry loop under `repo-context/`
 * (docs/specs/02-axe-harness.md § Boucles). Not verified on real data: none of the four
 * calibration profiles has a loop; only the maison fixtures exercise these patterns.
 *
 * RETRY and BOUND are kept independent on purpose (Codex review of PR #19): `attempt` used
 * to appear in both lists, so a bare declaration such as `MAX_ATTEMPTS = 3` — no rerun of
 * anything — satisfied both and was wrongly promoted to a loop. RETRY now only matches an
 * actual restart construct; a bound token such as `max_attempts` or `attempts <= 3` can no
 * longer satisfy it by itself.
 */
final class LoopPatterns
{
    /** An actual restart construct: retry, until, while, rerun, or a `for … in $(seq …)` loop. */
    public const string RETRY = '/\bretry\b|\buntil\b|\bwhile\b|\brerun\b|\bfor\b\s+\S+\s+\bin\b\s*\$\(\s*seq\b/i';

    /** A visible cap: a named budget constant, or a comparison against an integer. */
    public const string BOUND = '/max_attempts|max-retries|max_retries|MAX_ITER|budget|attempts?\s*[<>=]+\s*\d+/i';
}
