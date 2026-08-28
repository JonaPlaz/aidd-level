<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Profile;

use AiddLevel\Domain\Profile\PullRequest;
use AiddLevel\Domain\Profile\PullRequests;

/**
 * Reads `pull-requests.json`, a top-level list of individual pull requests
 * (docs/specs/00-vue-ensemble.md § 3): corroborates the git-activity.json medians, never
 * decides an axis on its own. An entry missing one of the fields the domain models
 * (`number`, `merged`, `commits`) is skipped rather than failing the whole read.
 */
final class PullRequestsReader
{
    /**
     * @param array<mixed> $items the already-decoded content of pull-requests.json
     */
    public static function read(array $items): PullRequests
    {
        $pullRequests = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $number = $item['number'] ?? null;
            $merged = $item['merged'] ?? null;
            $commits = $item['commits'] ?? null;

            if (!is_int($number) || !is_bool($merged) || !is_int($commits)) {
                continue;
            }

            $pullRequests[] = new PullRequest(number: $number, merged: $merged, commits: $commits);
        }

        return new PullRequests(items: $pullRequests);
    }
}
