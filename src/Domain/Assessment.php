<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The final verdict for one profile: a status (docs/specs/05-robustesse.md § Trois statuts
 * de sortie), the level reached when one applies, the axis or axes that cap it, every axis
 * verdict, the gestures towards the next level, and free-form notes (corroboration,
 * declared/present mismatches, quality-prerequisite figures — never part of the calculation).
 */
final readonly class Assessment
{
    /**
     * @param list<Axis>           $cappingAxes
     * @param list<AxisVerdict>    $verdicts
     * @param list<Recommendation> $recommendations
     * @param list<string>         $notes
     */
    public function __construct(
        public AssessmentStatus $status,
        public ?Level $level,
        public array $cappingAxes,
        public array $verdicts,
        public array $recommendations,
        public array $notes,
    ) {
    }
}
