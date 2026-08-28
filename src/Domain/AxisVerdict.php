<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Confidence\Confidence;

/**
 * What one AxisEvaluator concludes for one axis: the level reached (the confirmed floor,
 * even when confidence is a Range), how sure that is, and the evidence that supports it.
 */
final readonly class AxisVerdict
{
    /**
     * @param list<Evidence> $evidences
     * @param list<Note>     $notes
     */
    public function __construct(
        public Axis $axis,
        public Level $level,
        public Confidence $confidence,
        public array $evidences,
        public array $notes = [],
    ) {
    }
}
