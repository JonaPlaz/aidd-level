<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `git-activity.json`: the spine every axis resolves from (docs/specs/00-vue-ensemble.md § 3).
 * Required for the gate to pass (docs/specs/05-robustesse.md § Gate), but every field inside
 * is nullable — a missing field redescends the concerned axis to low confidence or marks it
 * not observable, it never breaks the gate.
 */
final readonly class GitActivity
{
    public function __construct(
        public ?Period $period,
        public ?int $pullRequestsTotal,
        public ?int $medianFilesChanged,
        public ?int $medianLinesChanged,
        public ?int $medianCorrectionCommitsAfterOpen,
        public ?int $mergedWithoutHumanEditAfterOpen,
        public ?float $aiCoauthoredRatio,
        public ?int $maxConcurrentBranches,
        public ?int $medianConcurrentBranches,
        public ?ContextFiles $contextFiles,
    ) {
    }
}
