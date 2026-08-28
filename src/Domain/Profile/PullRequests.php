<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `pull-requests.json`: a short, last-page list of individual pull requests. Corroborates
 * the git-activity.json medians, never decides an axis on its own
 * (docs/specs/00-vue-ensemble.md § 3).
 */
final readonly class PullRequests
{
    /**
     * @param list<PullRequest> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
