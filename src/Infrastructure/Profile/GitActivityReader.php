<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Profile;

use AiddLevel\Domain\Profile\ContextFiles;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Period;

/**
 * Reads `git-activity.json` into the domain aggregate. Tolerant on purpose
 * (docs/specs/05-robustesse.md § Gate): every field is read independently, a missing or
 * malformed one becomes `null` rather than failing the whole read.
 */
final class GitActivityReader
{
    /**
     * @param array<mixed> $data the already-decoded content of git-activity.json
     */
    public static function read(array $data): GitActivity
    {
        $period = self::arrayAt($data, 'period');
        $pullRequests = self::arrayAt($data, 'pull_requests');
        $commits = self::arrayAt($data, 'commits');
        $parallelism = self::arrayAt($data, 'parallelism');
        $contextFiles = self::arrayAt($data, 'context_files');

        return new GitActivity(
            period: null !== $period ? new Period(
                from: self::stringOrNull($period['from'] ?? null),
                to: self::stringOrNull($period['to'] ?? null),
            ) : null,
            pullRequestsTotal: self::intOrNull($pullRequests['total'] ?? null),
            medianFilesChanged: self::floatOrNull($pullRequests['median_files_changed'] ?? null),
            medianLinesChanged: self::floatOrNull($pullRequests['median_lines_changed'] ?? null),
            medianCorrectionCommitsAfterOpen: self::floatOrNull($pullRequests['median_correction_commits_after_open'] ?? null),
            mergedWithoutHumanEditAfterOpen: self::intOrNull($pullRequests['merged_without_human_edit_after_open'] ?? null),
            aiCoauthoredRatio: self::floatOrNull($commits['ai_coauthored_ratio'] ?? null),
            maxConcurrentBranches: self::intOrNull($parallelism['max_concurrent_branches'] ?? null),
            medianConcurrentBranches: self::floatOrNull($parallelism['median_concurrent_branches'] ?? null),
            contextFiles: null !== $contextFiles ? new ContextFiles(
                agentsMd: self::boolOrNull($contextFiles['agents_md'] ?? null),
                rules: self::intOrNull($contextFiles['rules_count'] ?? null),
                skills: self::intOrNull($contextFiles['skills_count'] ?? null),
                hooks: self::intOrNull($contextFiles['hooks_count'] ?? null),
                agents: self::intOrNull($contextFiles['agents_count'] ?? null),
            ) : null,
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    private static function arrayAt(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_float($value) ? (int) $value : null);
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_float($value) || is_int($value) ? (float) $value : null;
    }

    private static function boolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
