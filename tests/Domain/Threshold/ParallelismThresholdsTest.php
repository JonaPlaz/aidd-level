<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Threshold;

use AiddLevel\Domain\Level;
use AiddLevel\Domain\Threshold\ParallelismThresholds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParallelismThresholdsTest extends TestCase
{
    /**
     * @return iterable<string, array{float, Level}>
     */
    public static function medians(): iterable
    {
        yield 'none' => [0.0, Level::White];
        yield 'some, between none and one, an even-sample median' => [0.5, Level::Green];
        yield 'some, lower bound' => [1.0, Level::Green];
        yield 'some, upper bound' => [2.0, Level::Green];
        yield 'some, an even-sample median' => [2.5, Level::Green];
        yield 'habitual, on the boundary' => [3.0, Level::Gold];
        yield 'habitual, above the boundary' => [4.0, Level::Gold];
    }

    #[Test]
    #[DataProvider('medians')]
    public function levelForMedianPinsEachBoundary(float $median, Level $expected): void
    {
        self::assertSame($expected, ParallelismThresholds::levelForMedian($median));
    }
}
