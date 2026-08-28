<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The four axes of the AIDD grid (docs/specs/00-vue-ensemble.md § 2).
 * Adding an axis is a new AxisEvaluator; LevelRule does not change.
 */
enum Axis
{
    case Size;
    case Harness;
    case Intervention;
    case Parallelism;
}
