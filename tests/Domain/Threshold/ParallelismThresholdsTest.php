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
     * @return iterable<string, array{int, Level}>
     */
    public static function medians(): iterable
    {
        yield 'none' => [0, Level::White];
        yield 'some, lower bound' => [1, Level::Green];
        yield 'some, upper bound' => [2, Level::Green];
        yield 'habitual, on the boundary' => [3, Level::Gold];
        yield 'habitual, above the boundary' => [4, Level::Gold];
    }

    #[Test]
    #[DataProvider('medians')]
    public function levelForMedianPinsEachBoundary(int $median, Level $expected): void
    {
        self::assertSame($expected, ParallelismThresholds::levelForMedian($median));
    }
}
