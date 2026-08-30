<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Profile\RepoFile;

/**
 * A restart token that names a relance (`retry`, `rerun`, `until`) with no bound anywhere in
 * its proximity window — a risk, not a loop, but a fact worth rendering
 * (docs/specs/02-axe-harness.md § 5 « Une note nouvelle, et une seule »).
 */
final readonly class UnboundedRetryMatch
{
    public function __construct(
        public RepoFile $file,
        public int $line,
    ) {
    }
}
