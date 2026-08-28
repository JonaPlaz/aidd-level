<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Exception;

use AiddLevel\Domain\Profile\ProfileIdentity;

/**
 * Raised by a ProfileSource when the gate (docs/specs/05-robustesse.md § Gate) is broken:
 * the path is not a readable directory, profile.json or git-activity.json is missing or
 * invalid, or git-activity.json has no pull request at all. Carries the named prerequisite
 * that failed and, when it was already readable before the gate broke (e.g. profile.json is
 * valid but git-activity.json is not), the partial identity — so a NotAssessable Assessment
 * can report what was read anyway (docs/specs/05-robustesse.md § Trois statuts de sortie)
 * without the caller reopening infrastructure files.
 */
final class ProfileNotAssessable extends \RuntimeException implements DomainException
{
    public function __construct(
        public readonly string $missingPrerequisite,
        public readonly string $path,
        public readonly ?ProfileIdentity $partialIdentity = null,
    ) {
        parent::__construct(sprintf('Profile at "%s" is not assessable: %s', $path, $missingPrerequisite));
    }
}
