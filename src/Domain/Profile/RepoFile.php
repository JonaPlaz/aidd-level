<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * One file under `repo-context/`: its path relative to that folder, and its raw content.
 */
final readonly class RepoFile
{
    public function __construct(
        public string $path,
        public string $content,
    ) {
    }
}
