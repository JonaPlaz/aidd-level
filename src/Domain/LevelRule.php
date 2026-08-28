<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Confidence\Range;

/**
 * « Un niveau n'est atteint que si tous ses axes le sont » → the level is the minimum of
 * the axis verdicts (docs/specs/00-vue-ensemble.md § 2). Ties at the minimum are kept, not
 * collapsed to one axis (§ 06, rule 3: an ex-aequo is said as such, never averaged).
 *
 * When a verdict carries a Range confidence, its own floor and ceiling are used instead of
 * its single level; the result floor is the minimum of every axis floor, the result ceiling
 * the minimum of every axis ceiling (docs/specs/05-robustesse.md § Trois statuts de sortie).
 */
final class LevelRule
{
    /**
     * @param list<AxisVerdict> $verdicts
     */
    public function apply(array $verdicts): LevelRuleResult
    {
        if ($verdicts === []) {
            throw new \InvalidArgumentException('LevelRule::apply() requires at least one axis verdict.');
        }

        $floor = null;
        foreach ($verdicts as $verdict) {
            $verdictFloor = $this->floorOf($verdict);
            if (null === $floor || $verdictFloor->rank() < $floor->rank()) {
                $floor = $verdictFloor;
            }
        }

        $cappingAxes = [];
        foreach ($verdicts as $verdict) {
            if ($this->floorOf($verdict) === $floor) {
                $cappingAxes[] = $verdict->axis;
            }
        }

        $ceiling = null;
        foreach ($verdicts as $verdict) {
            $verdictCeiling = $this->ceilingOf($verdict);
            if (null === $ceiling || $verdictCeiling->rank() < $ceiling->rank()) {
                $ceiling = $verdictCeiling;
            }
        }

        return new LevelRuleResult($floor, $ceiling, $cappingAxes);
    }

    private function floorOf(AxisVerdict $verdict): Level
    {
        return $verdict->confidence instanceof Range ? $verdict->confidence->floor : $verdict->level;
    }

    private function ceilingOf(AxisVerdict $verdict): Level
    {
        return $verdict->confidence instanceof Range ? $verdict->confidence->ceiling : $verdict->level;
    }
}
