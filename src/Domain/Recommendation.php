<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * One gesture towards the next level for one axis. The wording itself is a fixed table
 * looked up by (axis, targetLevel) — no LLM drafting (docs/specs/06-sortie-et-progression.md
 * § Table des gestes). Structure only: the lookup table lives with the recommender that
 * builds Assessment.recommendations, not in this chantier.
 */
final readonly class Recommendation
{
    public function __construct(
        public Axis $axis,
        public Level $targetLevel,
        public string $gesture,
    ) {
    }
}
