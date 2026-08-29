<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Application;

use AiddLevel\Domain\Exception\ProfileNotAssessable;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\ProfileSource;

/**
 * A ProfileSource test double that returns a fixed Profile (or raises a fixed
 * ProfileNotAssessable), so EvaluateProfileHandlerTest can drive fixtures the four real
 * profiles never exercise (docs/specs/05-robustesse.md § Planchers d'échantillon: "aucune
 * n'est sourcée ... les fixtures maison le couvrent").
 */
final class InMemoryProfileSource implements ProfileSource
{
    public function __construct(
        private readonly ?Profile $profile = null,
        private readonly ?ProfileNotAssessable $failure = null,
    ) {
    }

    public function load(string $path): Profile
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        \assert(null !== $this->profile);

        return $this->profile;
    }
}
