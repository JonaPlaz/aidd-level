<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

/**
 * Regex motifs used by `LoopDetector` to spot a bounded retry loop under `repo-context/`
 * (docs/specs/02-axe-harness.md § Boucles, § « détection resserrée »). Not verified on real
 * data beyond the three real `repo-context/` folders available (§ 8): only the maison
 * fixtures exercise most of these patterns.
 *
 * RETRY and BOUND stay independent on purpose (Codex review of PR #19): `attempt` used to
 * appear in both lists, so a bare declaration such as `MAX_ATTEMPTS = 3` — no rerun of
 * anything — satisfied both and was wrongly promoted to a loop. A bound token can no longer
 * satisfy RETRY by itself. The counted-loop form (§ 4) is the one deliberate exception: it
 * carries both roles at once, because the cap is *inside* the expression that restarts.
 */
final class LoopPatterns
{
    /**
     * Names a restart explicitly — `retry`, `rerun`, `until` — never `while` and never a
     * counted loop (docs/specs/02-axe-harness.md § 5): only these tokens can trigger the
     * « relance non bornée » note. The right edge is a negative lookahead for a letter
     * (`(?![a-zA-Z])`), not a plain `\b`: `retry_deploy` still counts (the underscore is not
     * a letter, fixture FP1b), but `retryable` and `untilted` — a different word that merely
     * starts the same way — do not (Codex review of PR #50).
     */
    public const string RETRY_NAMED = '/\bretry(?![a-zA-Z])|\brerun(?![a-zA-Z])|\buntil(?![a-zA-Z])/i';

    /**
     * Any restart construct: the three named ones above, plus `while`, plus a
     * `for … in $(seq …)` / `for … in {…}` loop in any form — including the unrestricted
     * ones (`seq 100 -1 1`, `{-100..20}`) that § 4 still counts as a restart looking for a
     * separate bound, even though they never satisfy the counted-loop bound themselves.
     */
    public const string RETRY = '/\bretry(?![a-zA-Z])|\brerun(?![a-zA-Z])|\buntil(?![a-zA-Z])|\bwhile\b'
        .'|\bfor\b\s+\S+\s+\bin\b\s*(?:\$\(\s*seq\b|\{)/i';

    /**
     * A visible cap: a named budget constant, a comparison against `attempt(s)`, a numeric
     * shell test (`-ge 3`, added — without it no real Makefile `until … ; [ $n -ge 3 ]` is
     * detectable), or a GitLab-style `max: N` / `max=N` (added — `retry: / max: 2`, verified
     * at the GitLab CI/CD YAML doc on 2026-08-30).
     */
    public const string BOUND = '/max_attempts|max-retries|max_retries|MAX_ITER|budget'
        .'|attempts?\s*[<>=]+\s*\d+|-(?:ge|gt|le|lt|eq)\s+\d+|\bmax\s*[:=]\s*\d+/i';

    /**
     * Counted-loop forms whose length is knowable without executing anything (§ 4): a
     * literal start of `1` and a literal step of `1`, so `seq 5 40` (start ≠ 1),
     * `seq 1 $MAX` (a variable) and `seq 1 0.5 3` (a float) never qualify — they fall back to
     * needing a separate bound in the window, like any other restart. Capture group 1 is the
     * full matched expression (rendered in the pointer); group 2 is the length `N`.
     *
     * @var list<string>
     */
    public const array COUNTED = [
        // for … in $(seq N) — length N.
        '/\bfor\s+\S+\s+in\s+(\$\(\s*seq\s+(\d+)\s*\))/i',
        // for … in $(seq 1 N) — literal start 1, length N.
        '/\bfor\s+\S+\s+in\s+(\$\(\s*seq\s+1\s+(\d+)\s*\))/i',
        // for … in {1..N} — literal start 1, length N.
        '/\bfor\s+\S+\s+in\s+(\{1\.\.(\d+)\})/i',
    ];
}
