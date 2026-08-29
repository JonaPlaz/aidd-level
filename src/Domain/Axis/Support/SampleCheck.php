<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Support;

use AiddLevel\Domain\Confidence\Confidence;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;

/**
 * The pull-request sample floor pattern shared by Size, Intervention and Parallelism
 * (docs/specs/05-robustesse.md § Planchers d'échantillon): at or above the floor, the
 * verdict is `Confirmed`; short of it, the axis sits in a `Range` down to `$floor` and up to
 * `$ceiling`, with the missing pull-request count carried for the note the caller writes.
 * Only the arithmetic is shared — each evaluator still writes its own note text.
 */
final class SampleCheck
{
    public static function confidence(int $total, int $floor, Level $level, Level $ceiling): Confidence
    {
        if ($total >= $floor) {
            return new Confirmed();
        }

        return new Range($level, $ceiling, $floor - $total);
    }
}
