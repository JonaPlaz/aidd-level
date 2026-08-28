<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Threshold;

use AiddLevel\Domain\Threshold\SampleFloors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SampleFloorsTest extends TestCase
{
    #[Test]
    public function pinsEachSampleFloor(): void
    {
        self::assertSame(5, SampleFloors::MIN_PR_SAMPLE);
        self::assertSame(12, SampleFloors::MIN_PR_SAMPLE_ABSENCE);
        self::assertSame(5, SampleFloors::PARALLELISM_MIN_PR);
        self::assertSame(1, SampleFloors::GATE_MIN_PR);
    }
}
