<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Confidence;

/**
 * How sure an AxisVerdict is: either Confirmed, or a Range when the sample is too small
 * to settle on a single level (docs/specs/05-robustesse.md § Trois statuts de sortie).
 */
interface Confidence
{
}
