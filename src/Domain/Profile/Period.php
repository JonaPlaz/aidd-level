<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `git-activity.json › period`: the window the measures cover. ISO date strings, kept as
 * given — the domain never parses or recomputes dates.
 */
final readonly class Period
{
    public function __construct(
        public ?string $from,
        public ?string $to,
    ) {
    }
}
