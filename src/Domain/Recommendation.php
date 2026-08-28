<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * One gesture towards the next level for one axis. The wording itself is a fixed table
 * looked up by (axis, targetLevel) — no LLM drafting (docs/specs/06-sortie-et-progression.md
 * § Table des gestes). Structure only: the lookup table lives with the recommender that
 * builds Assessment.recommendations, not in this chantier.
 *
 * `proofField` is the field that must move to validate the gesture (docs/specs/06 § La
 * preuve attendue) — the pointer the "prochaine quête" cites alongside the first `Evidence`
 * already observed for the axis. It is a bare field name, not a `Pointer`: nothing has been
 * observed there yet, there is nothing to point at until the gesture is done.
 */
final readonly class Recommendation
{
    public function __construct(
        public Axis $axis,
        public Level $targetLevel,
        public string $gesture,
        public string $proofField,
    ) {
    }
}
