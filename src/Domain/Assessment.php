<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Profile\ProfileIdentity;

/**
 * The final verdict for one profile: a status (docs/specs/05-robustesse.md § Trois statuts
 * de sortie), the identity read (when the gate allowed it), the level reached when one
 * applies, the axis or axes that cap it, every axis verdict, the gestures towards the next
 * level, and free-form notes (corroboration, declared/present mismatches, quality-prerequisite
 * figures — never part of the calculation).
 *
 * `identity` is null only for a `NotAssessable` result whose gate broke before profile.json
 * could be read at all — the renderer still needs `id`/`role` for the output heading
 * (docs/specs/06-sortie-et-progression.md § Format de sortie, e.g. "arthur (développeur
 * indépendant)") on every other status, including a gate failure that read that far.
 *
 * `level` is the global floor (LevelRuleResult::$floor); `ceiling` is the global ceiling
 * (LevelRuleResult::$ceiling) — equal to `level` once `status` is `Evaluated`, above it when
 * `LowConfidence`, and both null when `NotAssessable`. Keeping the ceiling here spares the
 * renderer from recomputing the minimum over `verdicts` on its own
 * (docs/specs/05-robustesse.md § Trois statuts de sortie).
 */
final readonly class Assessment
{
    /**
     * @param list<Axis>           $cappingAxes
     * @param list<AxisVerdict>    $verdicts
     * @param list<Recommendation> $recommendations
     * @param list<Note>           $notes
     */
    public function __construct(
        public AssessmentStatus $status,
        public ?ProfileIdentity $identity,
        public ?Level $level,
        public ?Level $ceiling,
        public array $cappingAxes,
        public array $verdicts,
        public array $recommendations,
        public array $notes,
    ) {
    }
}
