<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Exception\ProfileNotAssessable;
use AiddLevel\Domain\Profile\Profile;

/**
 * The only entry boundary of the domain (docs/specs/00-vue-ensemble.md § 4.1): reads a
 * profile folder and returns the aggregate, or throws when the gate
 * (docs/specs/05-robustesse.md § Gate) is broken.
 */
interface ProfileSource
{
    /**
     * @throws ProfileNotAssessable when the path is not a readable directory, profile.json
     *                              or git-activity.json is missing or invalid JSON, or
     *                              git-activity.json has no pull request at all
     */
    public function load(string $path): Profile;
}
