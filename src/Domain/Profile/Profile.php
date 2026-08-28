<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * A profile folder read into memory (docs/specs/00-vue-ensemble.md § 3): pre-aggregated
 * measures, never a git repository. `identity` and `gitActivity` are always present once the
 * gate has passed (docs/specs/05-robustesse.md § Gate); every other piece is optional —
 * profiles never carry all eight pieces at once.
 *
 * `presentPieces` is the actual directory inventory (names as in `ProfileIdentity::available`,
 * e.g. `git-activity.json`, `code/`, `session.md`), kept alongside the declared list so the
 * declared/present mismatch (docs/specs/05-robustesse.md § Cohérence annoncé / présent) can be
 * reported even for pieces this aggregate does not model (`code/`, `session.md`).
 */
final readonly class Profile
{
    /**
     * @param list<string> $presentPieces
     */
    public function __construct(
        public ProfileIdentity $identity,
        public GitActivity $gitActivity,
        public ?RepoContext $repoContext = null,
        public ?SonarMeasures $sonarMeasures = null,
        public ?Declarative $declarative = null,
        public ?PullRequests $pullRequests = null,
        public array $presentPieces = [],
    ) {
    }
}
