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
     * @return iterable<string, array{float, Level}>
     */
    public static function medians(): iterable
    {
        yield 'majority, on the boundary' => [3.0, Level::Red];
        yield 'majority, above the boundary' => [4.0, Level::Red];
        yield 'partial' => [2.0, Level::Blue];
        yield 'partial, an even-sample median' => [2.5, Level::Blue];
        yield 'key steps' => [1.0, Level::Copper];
        yield 'key steps, an even-sample median' => [1.5, Level::Copper];
        yield 'key steps, between never and key steps' => [0.5, Level::Copper];
        yield 'never' => [0.0, Level::Silver];
    }

    #[Test]
    #[DataProvider('medians')]
    public function levelForMedianPinsEachBoundary(float $median, Level $expected): void
    {
        self::assertSame($expected, InterventionThresholds::levelForMedian($median));
    }
}
