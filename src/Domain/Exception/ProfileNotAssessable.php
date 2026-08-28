<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Exception;

/**
 * Raised by a ProfileSource when the gate (docs/specs/05-robustesse.md § Gate) is broken:
 * the path is not a readable directory, profile.json or git-activity.json is missing or
 * invalid, or git-activity.json has no pull request at all. Carries the named prerequisite
 * that failed, so the caller can turn it into a NotAssessable Assessment without guessing.
 */
final class ProfileNotAssessable extends \RuntimeException implements DomainException
{
    public function __construct(
        public readonly string $missingPrerequisite,
        public readonly string $path,
    ) {
        parent::__construct(sprintf('Profile at "%s" is not assessable: %s', $path, $missingPrerequisite));
    }
}
