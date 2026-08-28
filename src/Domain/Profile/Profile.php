<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * A profile folder read into memory (docs/specs/00-vue-ensemble.md § 3): pre-aggregated
 * measures, never a git repository. `identity` and `gitActivity` are always present once the
 * gate has passed (docs/specs/05-robustesse.md § Gate); every other piece is optional —
 * profiles never carry all eight pieces at once.
 */
final readonly class Profile
{
    public function __construct(
        public ProfileIdentity $identity,
        public GitActivity $gitActivity,
        public ?RepoContext $repoContext = null,
        public ?SonarMeasures $sonarMeasures = null,
        public ?Declarative $declarative = null,
        public ?PullRequests $pullRequests = null,
    ) {
    }
}
