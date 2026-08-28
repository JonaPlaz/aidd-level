<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;

/**
 * Finds a bounded retry loop under `repo-context/` (docs/specs/02-axe-harness.md § Boucles):
 * a restart pattern and a bound pattern in the same eligible file. A restart without a bound
 * is a risk, not a loop in the grid's sense — both must be present.
 */
final class LoopDetector
{
    /** Eligible orchestration surfaces: CI, Makefile, scripts, common script extensions. */
    private const array ELIGIBLE_EXTENSIONS = ['.sh', '.js', '.ts', '.py', '.yml', '.yaml'];

    /** `docs/` never counts, even a brainstorm that talks about retries (arthur's fixture). */
    private const string EXCLUDED_PREFIX = 'docs/';

    public static function detect(RepoContext $repoContext): ?RepoFile
    {
        foreach ($repoContext->files as $file) {
            if (self::isEligible($file->path) && self::hasBoundedRetry($file->content)) {
                return $file;
            }
        }

        return null;
    }

    private static function isEligible(string $path): bool
    {
        if (str_starts_with($path, self::EXCLUDED_PREFIX)) {
            return false;
        }

        if (str_starts_with($path, '.github/workflows/')) {
            return true;
        }

        if (str_starts_with($path, 'scripts/')) {
            return true;
        }

        if ('Makefile' === $path || str_ends_with($path, '/Makefile')) {
            return true;
        }

        foreach (self::ELIGIBLE_EXTENSIONS as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }

        return false;
    }

    private static function hasBoundedRetry(string $content): bool
    {
        return 1 === preg_match(LoopPatterns::RETRY, $content)
            && 1 === preg_match(LoopPatterns::BOUND, $content);
    }
}
