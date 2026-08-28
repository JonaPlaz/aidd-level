<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Progression;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Recommendation;

/**
 * Orders the capping axes by causal actionability, never by grid column
 * (docs/specs/06-sortie-et-progression.md § Cinq règles, rule 5: Harness first — actionable
 * today; Parallelism second — actionable but conditioned by Harness; Intervention third —
 * mutable, not actionable directly; Size last — mutable, not actionable, never its own
 * gesture). One `Recommendation` per capping axis, looked up in `RecommendationTable`.
 */
final class RecommendationPolicy
{
    /** @var list<Axis> */
    private const array ACTIONABILITY_ORDER = [
        Axis::Harness,
        Axis::Parallelism,
        Axis::Intervention,
        Axis::Size,
    ];

    public function __construct(
        private readonly RecommendationTable $table = new RecommendationTable(),
    ) {
    }

    /**
     * @param list<Axis> $cappingAxes
     *
     * @return list<Recommendation>
     */
    public function recommend(array $cappingAxes, Level $targetLevel): array
    {
        $recommendations = [];
        foreach (self::ACTIONABILITY_ORDER as $axis) {
            if (!\in_array($axis, $cappingAxes, true)) {
                continue;
            }

            $recommendations[] = new Recommendation($axis, $targetLevel, $this->table->gestureFor($axis, $targetLevel));
        }

        return $recommendations;
    }
}
