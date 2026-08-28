<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `sonar-measures.json`: a quality-prerequisite note, never an axis and never part of the
 * calculation (docs/specs/05-robustesse.md § Sonar — prérequis, hors calcul). Values are
 * cited, not judged: no threshold here.
 */
final readonly class SonarMeasures
{
    public function __construct(
        public ?float $duplication,
        public ?float $coverage,
    ) {
    }
}
