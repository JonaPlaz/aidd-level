<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Threshold;

use AiddLevel\Domain\Threshold\SizeBand;
use AiddLevel\Domain\Threshold\SizeThresholds;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SizeThresholdsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, SizeBand}>
     */
    public static function fileBoundaries(): iterable
    {
        yield 'S upper bound' => [2, SizeBand::S];
        yield 'M lower bound' => [3, SizeBand::M];
        yield 'M upper bound' => [8, SizeBand::M];
        yield 'L lower bound' => [9, SizeBand::L];
        yield 'L upper bound' => [20, SizeBand::L];
        yield 'XL lower bound' => [21, SizeBand::XL];
    }

    #[Test]
    #[DataProvider('fileBoundaries')]
    public function bandForFilesPinsEachBoundary(int $files, SizeBand $expected): void
    {
        self::assertSame($expected, SizeThresholds::bandForFiles($files));
    }

    /**
     * @return iterable<string, array{float, SizeBand}>
     */
    public static function lineBoundaries(): iterable
    {
        yield 'S upper bound' => [60.0, SizeBand::S];
        yield 'M lower bound' => [61.0, SizeBand::M];
        yield 'M upper bound' => [210.0, SizeBand::M];
        yield 'L lower bound' => [211.0, SizeBand::L];
        yield 'L upper bound' => [1000.0, SizeBand::L];
        yield 'XL lower bound' => [1001.0, SizeBand::XL];
    }

    #[Test]
    #[DataProvider('lineBoundaries')]
    public function bandForLinesPinsEachBoundary(float $lines, SizeBand $expected): void
    {
        self::assertSame($expected, SizeThresholds::bandForLines($lines));
    }

    #[Test]
    public function bandForLinesAcceptsAFractionalMedianLikeBohorts(): void
    {
        // bohort: median_lines_changed = 251.5 (docs/specs/01-axe-taille.md § Signal).
        self::assertSame(SizeBand::L, SizeThresholds::bandForLines(251.5));
    }
}
