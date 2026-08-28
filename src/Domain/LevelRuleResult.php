<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The outcome of LevelRule::apply(): a floor (the minimum of the verdicts), a ceiling
 * (floor === ceiling when every verdict is Confirmed), and the axis or axes — ties kept —
 * that hold the floor down.
 */
final readonly class LevelRuleResult
{
    /**
     * @param list<Axis> $cappingAxes
     */
    public function __construct(
        public Level $floor,
        public Level $ceiling,
        public array $cappingAxes,
    ) {
    }

    public function isConfirmed(): bool
    {
        return $this->floor === $this->ceiling;
    }
}
