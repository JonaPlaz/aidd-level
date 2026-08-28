<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `repo-context/`: cites the proof of the Harness axis and carries the loop detection
 * (docs/specs/02-axe-harness.md § Boucles). Absent entirely when the piece is not provided.
 */
final readonly class RepoContext
{
    /**
     * @param list<RepoFile> $files
     */
    public function __construct(
        public array $files,
    ) {
    }
}
