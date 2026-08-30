<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;

/**
 * Finds a bounded retry loop under `repo-context/` (docs/specs/02-axe-harness.md § Boucles,
 * § « détection resserrée », chantier 14): a restart pattern and a bound pattern within
 * `LoopThresholds::PROXIMITY_LINES` lines of each other, in the same eligible orchestration
 * surface (§ 3). A restart without a nearby bound is a risk, not a loop — both must be close.
 *
 * Files are scanned in the order `RepoContext` provides them (`RepoContextReader` sorts
 * paths), and — within a file — comments are stripped with a two-state automaton (§ 2) before
 * any pattern is applied, so a comment that merely *talks about* a retry never counts.
 */
final class LoopDetector
{
    /**
     * `docs/` never counts, even a brainstorm that talks about retries (arthur's fixture) —
     * checked first, before any other rule (§ 3).
     */
    private const string EXCLUDED_PREFIX = 'docs/';

    /**
     * A segment of the path that marks it as an orchestration script, whatever the tool that
     * put it there (`.claude/hooks/`, `.cursor/hooks/`, `.git/hooks/` all match `hooks/` —
     * never the marque itself, spec 00 § 3).
     *
     * @var list<string>
     */
    private const array SCRIPT_DIR_SEGMENTS = ['scripts', 'bin', 'tools', 'hooks', '.husky'];

    /**
     * @var list<string>
     */
    private const array SCRIPT_EXTENSIONS = ['.sh', '.bash', '.js', '.mjs', '.ts', '.py'];

    public static function detect(RepoContext $repoContext): ?LoopMatch
    {
        foreach ($repoContext->files as $file) {
            if (!self::isEligible($file)) {
                continue;
            }

            $scan = self::scan($file);
            $pair = self::findPair($scan['retries'], $scan['bounds']);
            if (null === $pair) {
                continue;
            }

            return new LoopMatch(
                $file,
                $pair['retry']['line'],
                $pair['retry']['token'],
                $pair['bound']['line'],
                $pair['bound']['token'],
            );
        }

        return null;
    }

    /**
     * The first eligible file, in reading order, that names a relance (`retry`, `rerun`,
     * `until` — never `while`, never a counted loop, § 5) with no bound anywhere in its
     * window. Only meant to be consulted when `detect()` found no loop at all.
     */
    public static function detectUnboundedRetry(RepoContext $repoContext): ?UnboundedRetryMatch
    {
        foreach ($repoContext->files as $file) {
            if (!self::isEligible($file)) {
                continue;
            }

            $scan = self::scan($file);
            $line = self::findUnboundedNamedRetryLine($scan['retries'], $scan['bounds']);
            if (null === $line) {
                continue;
            }

            return new UnboundedRetryMatch($file, $line);
        }

        return null;
    }

    /**
     * A whitelist of orchestration-file roles (docs/specs/02-axe-harness.md § 3), never of
     * marques: CI (GitHub workflows, GitHub composite actions, GitLab CI), Make, and scripts
     * living under a recognizable orchestration directory or at the repo-context root.
     */
    private static function isEligible(RepoFile $file): bool
    {
        $path = $file->path;

        if (str_starts_with($path, self::EXCLUDED_PREFIX)) {
            return false;
        }

        if (1 === preg_match('#^\.github/workflows/[^/]+\.ya?ml$#i', $path)) {
            return true;
        }

        if (1 === preg_match('#^\.github/actions/[^/]+/action\.ya?ml$#i', $path)) {
            return true;
        }

        if ('.gitlab-ci.yml' === $path) {
            return true;
        }

        $basename = basename($path);
        if (in_array($basename, ['Makefile', 'makefile', 'GNUmakefile'], true) || str_ends_with($basename, '.mk')) {
            return true;
        }

        if (!str_contains($path, '/') && str_ends_with($path, '.sh')) {
            return true;
        }

        $segments = explode('/', $path);
        array_pop($segments);
        if ([] === array_intersect($segments, self::SCRIPT_DIR_SEGMENTS)) {
            return false;
        }

        foreach (self::SCRIPT_EXTENSIONS as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }

        return !str_contains($basename, '.') && self::hasShebang($file->content);
    }

    private static function hasShebang(string $content): bool
    {
        $firstLine = strtok($content, "\r\n");

        return false !== $firstLine && str_starts_with($firstLine, '#!');
    }

    /**
     * @return array{
     *     retries: list<array{line: int, token: string, named: bool}>,
     *     bounds: list<array{line: int, token: string}>,
     * }
     */
    private static function scan(RepoFile $file): array
    {
        $retries = [];
        $bounds = [];

        foreach (self::activeLines($file->content) as $lineNumber => $line) {
            $countedToken = self::matchCounted($line);
            if (null !== $countedToken) {
                // The counted loop carries both roles at once: the cap is inside the
                // expression that restarts (§ 4).
                $retries[] = ['line' => $lineNumber, 'token' => $countedToken, 'named' => false];
                $bounds[] = ['line' => $lineNumber, 'token' => $countedToken];
                continue;
            }

            $retryToken = self::firstMatch(LoopPatterns::RETRY, $line);
            if (null !== $retryToken) {
                $named = null !== self::firstMatch(LoopPatterns::RETRY_NAMED, $line);
                $retries[] = ['line' => $lineNumber, 'token' => $retryToken, 'named' => $named];
            }

            $boundToken = self::firstMatch(LoopPatterns::BOUND, $line);
            if (null !== $boundToken) {
                $bounds[] = ['line' => $lineNumber, 'token' => $boundToken];
            }
        }

        return ['retries' => $retries, 'bounds' => $bounds];
    }

    /**
     * Ligne à ligne, avec un état de bloc (§ 2): a line-comment (`#`, `//`) is dropped whole;
     * `/*` opens a block in which *every* line is dropped, whatever it contains, until `*\/`
     * closes it — or the file ends, in which case the rest of the file is dropped too. Line
     * numbers of the lines kept never move: they are the real line number of the file.
     *
     * @return array<int, string> keyed by the real 1-based line number
     */
    private static function activeLines(string $content): array
    {
        if ('' === $content) {
            return [];
        }

        // Binary or invalid-UTF-8 content: checked explicitly, before any regex touches it,
        // rather than relying on `preg_split`/`preg_match` to fail (docs/specs/02-axe-harness.md
        // § Cas dégradés — no match, never an exception; Codex review of PR #50).
        if (str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if (false === $lines) {
            return [];
        }

        $active = [];
        $inBlock = false;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if ($inBlock) {
                if (str_contains($line, '*/')) {
                    $inBlock = false;
                }
                continue;
            }

            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, '//')) {
                continue;
            }

            $openPos = strpos($line, '/*');
            if (false !== $openPos) {
                // `/* generated */`: closed on the very same line, only that line is
                // dropped — the block state must not leak into the lines that follow
                // (Codex review of PR #50).
                $closePos = strpos($line, '*/', $openPos + 2);
                $inBlock = false === $closePos;
                continue;
            }

            $active[$lineNumber] = $line;
        }

        return $active;
    }

    private static function matchCounted(string $line): ?string
    {
        foreach (LoopPatterns::COUNTED as $pattern) {
            $result = @preg_match($pattern, $line, $matches);
            if (1 !== $result) {
                continue;
            }

            $length = (int) $matches[2];
            if ($length >= LoopThresholds::COUNTED_MIN && $length <= LoopThresholds::COUNTED_MAX) {
                return $matches[1];
            }
        }

        return null;
    }

    private static function firstMatch(string $pattern, string $line): ?string
    {
        $result = @preg_match($pattern, $line, $matches);
        if (1 !== $result) {
            return null;
        }

        return $matches[0];
    }

    /**
     * The valid pair (distance ≤ `LoopThresholds::PROXIMITY_LINES`) with the smallest
     * distance wins — a Makefile target literally named `retry:` must not steal a farther
     * pairing away from the `until` that actually restarts something closer to its bound
     * (TP1). Ties break on the earliest line, so the result stays deterministic.
     *
     * @param list<array{line: int, token: string, named: bool}> $retries
     * @param list<array{line: int, token: string}>              $bounds
     *
     * @return array{
     *     retry: array{line: int, token: string, named: bool},
     *     bound: array{line: int, token: string},
     * }|null
     */
    private static function findPair(array $retries, array $bounds): ?array
    {
        $best = null;
        $bestKey = null;

        foreach ($retries as $retry) {
            foreach ($bounds as $bound) {
                $distance = abs($retry['line'] - $bound['line']);
                if ($distance > LoopThresholds::PROXIMITY_LINES) {
                    continue;
                }

                $key = [$distance, min($retry['line'], $bound['line']), $retry['line'], $bound['line']];
                if (null === $bestKey || $key < $bestKey) {
                    $bestKey = $key;
                    $best = ['retry' => $retry, 'bound' => $bound];
                }
            }
        }

        return $best;
    }

    /**
     * The first named retry occurrence (in line order) with no bound anywhere within its
     * window — checked against every bound in the file, not just the ones already scanned,
     * since a bound may sit either before or after the retry it caps.
     *
     * @param list<array{line: int, token: string, named: bool}> $retries
     * @param list<array{line: int, token: string}>              $bounds
     */
    private static function findUnboundedNamedRetryLine(array $retries, array $bounds): ?int
    {
        foreach ($retries as $retry) {
            if (!$retry['named']) {
                continue;
            }

            $hasNearbyBound = false;
            foreach ($bounds as $bound) {
                if (abs($retry['line'] - $bound['line']) <= LoopThresholds::PROXIMITY_LINES) {
                    $hasNearbyBound = true;
                    break;
                }
            }

            if (!$hasNearbyBound) {
                return $retry['line'];
            }
        }

        return null;
    }
}
