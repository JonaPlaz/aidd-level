<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The outcome of LevelRule::apply(): a floor (the minimum of the verdicts), a ceiling
 * (equal to the floor only when every verdict is Confirmed), and the axis or axes — ties
 * kept — that hold the floor down.
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
        private bool $confirmed,
    ) {
    }

    /**
     * False as soon as any verdict carries a Range confidence, even when the global floor
     * and ceiling happen to collapse to the same level because another, Confirmed, axis
     * masks the uncertain one (docs/specs/05-robustesse.md § Trois statuts de sortie —
     * a masked Range must still yield `LowConfidence`, never `Evaluated`).
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }
}
