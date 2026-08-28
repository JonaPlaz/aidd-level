<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Profile\Profile;

/**
 * The extension point: one evaluator per axis. Adding an axis is one new class here;
 * LevelRule and the minimum computation do not change (docs/specs/00-vue-ensemble.md § 4.1).
 */
interface AxisEvaluator
{
    public function axis(): Axis;

    public function evaluate(Profile $profile): AxisVerdict;
}
