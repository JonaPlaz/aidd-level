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
     * @return iterable<string, array{float, SizeBand}>
     */
    public static function fileBoundaries(): iterable
    {
        yield 'S upper bound' => [2.0, SizeBand::S];
        yield 'between S and M, an even-sample median' => [2.5, SizeBand::M];
        yield 'M lower bound' => [3.0, SizeBand::M];
        yield 'M upper bound' => [8.0, SizeBand::M];
        yield 'L lower bound' => [9.0, SizeBand::L];
        yield 'L upper bound' => [20.0, SizeBand::L];
        yield 'XL lower bound' => [21.0, SizeBand::XL];
    }

    #[Test]
    #[DataProvider('fileBoundaries')]
    public function bandForFilesPinsEachBoundary(float $files, SizeBand $expected): void
    {
        self::assertSame($expected, SizeThresholds::bandForFiles($files));
    }

    #[Test]
    public function bandForFilesAcceptsAFractionalMedianFromAnEvenSample(): void
    {
        // An even-sized PR sample can legitimately yield a fractional median
        // (e.g. counts 2 and 3 → 2.5): median_files_changed must not crash on it.
        self::assertSame(SizeBand::M, SizeThresholds::bandForFiles(2.5));
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
