<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * One entry of `pull-requests.json`. Corroborates, never decides
 * (docs/specs/03-axe-intervention.md § Corroboration, jamais décision): `commits` is the
 * only field a corroboration check reads today.
 */
final readonly class PullRequest
{
    public function __construct(
        public int $number,
        public bool $merged,
        public int $commits,
    ) {
    }
}
