<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Confidence;

use AiddLevel\Domain\Level;

/**
 * The sample is too small to settle: the axis sits somewhere between a confirmed floor
 * and an unconfirmed ceiling, with a counted-out missing sample size
 * (docs/specs/05-robustesse.md § Trois statuts de sortie — « évalué, confiance basse »).
 */
final readonly class Range implements Confidence
{
    public function __construct(
        public Level $floor,
        public Level $ceiling,
        public int $missingSample,
    ) {
    }
}
