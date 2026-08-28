<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Threshold;

use AiddLevel\Domain\Level;
use AiddLevel\Domain\Threshold\InterventionThresholds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionThresholdsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, Level}>
     */
    public static function medians(): iterable
    {
        yield 'majority, on the boundary' => [3, Level::Red];
        yield 'majority, above the boundary' => [4, Level::Red];
        yield 'partial' => [2, Level::Blue];
        yield 'key steps' => [1, Level::Copper];
        yield 'never' => [0, Level::Silver];
    }

    #[Test]
    #[DataProvider('medians')]
    public function levelForMedianPinsEachBoundary(int $median, Level $expected): void
    {
        self::assertSame($expected, InterventionThresholds::levelForMedian($median));
    }
}
