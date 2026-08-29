<?php

declare(strict_types=1);

namespace AiddLevel\Application;

/**
 * The request handled by EvaluateProfileHandler (docs/specs/00-vue-ensemble.md § 4.2):
 * the single input a profile evaluation needs, the folder path
 * (docs/specs/05-robustesse.md § Gate, step 1).
 */
final readonly class EvaluateProfile
{
    public function __construct(
        public string $path,
    ) {
    }
}
