<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\LevelRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LevelRuleTest extends TestCase
{
    #[Test]
    public function levelIsTheMinimumOfEveryAxisVerdict(): void
    {
        $result = new LevelRule()->apply([
            $this->confirmed(Axis::Size, Level::Gold),
            $this->confirmed(Axis::Harness, Level::Copper),
            $this->confirmed(Axis::Intervention, Level::Blue),
            $this->confirmed(Axis::Parallelism, Level::Silver),
        ]);

        self::assertSame(Level::Blue, $result->floor);
        self::assertSame(Level::Blue, $result->ceiling);
        self::assertTrue($result->isConfirmed());
        self::assertSame([Axis::Intervention], $result->cappingAxes);
    }

    #[Test]
    public function keepsTiedAxesAtTheMinimumInsteadOfPickingOne(): void
    {
        $result = new LevelRule()->apply([
            $this->confirmed(Axis::Size, Level::Gold),
            $this->confirmed(Axis::Harness, Level::Copper),
            $this->confirmed(Axis::Intervention, Level::Copper),
            $this->confirmed(Axis::Parallelism, Level::Gold),
        ]);

        self::assertSame(Level::Copper, $result->floor);
        self::assertSame([Axis::Harness, Axis::Intervention], $result->cappingAxes);
    }

    #[Test]
    public function aRangeVerdictContributesItsOwnFloorAndCeiling(): void
    {
        $result = new LevelRule()->apply([
            $this->range(Axis::Size, floor: Level::Blue, ceiling: Level::Gold, missingSample: 2),
            $this->confirmed(Axis::Harness, Level::Green),
            $this->confirmed(Axis::Intervention, Level::Green),
            $this->confirmed(Axis::Parallelism, Level::Green),
        ]);

        // Floor: min(Blue, Green, Green, Green) = Blue, from the Size range.
        self::assertSame(Level::Blue, $result->floor);
        self::assertSame([Axis::Size], $result->cappingAxes);
        // Ceiling: min(Gold, Green, Green, Green) = Green, from the three confirmed axes.
        self::assertSame(Level::Green, $result->ceiling);
        self::assertFalse($result->isConfirmed());
    }

    #[Test]
    public function theGlobalFloorAndCeilingEachTakeTheMinimumAcrossDistinctRanges(): void
    {
        $result = new LevelRule()->apply([
            $this->range(Axis::Size, floor: Level::Copper, ceiling: Level::Silver, missingSample: 1),
            $this->range(Axis::Harness, floor: Level::Blue, ceiling: Level::Green, missingSample: 3),
        ]);

        // Floor: min(Copper, Blue) = Blue, held down by Harness.
        self::assertSame(Level::Blue, $result->floor);
        self::assertSame([Axis::Harness], $result->cappingAxes);
        // Ceiling: min(Silver, Green) = Green, from Harness too — a different axis could hold it.
        self::assertSame(Level::Green, $result->ceiling);
    }

    #[Test]
    public function refusesToRuleOnAnEmptyListOfVerdicts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LevelRule()->apply([]);
    }

    private function confirmed(Axis $axis, Level $level): AxisVerdict
    {
        return new AxisVerdict(
            axis: $axis,
            level: $level,
            confidence: new Confirmed(),
            evidences: [],
        );
    }

    private function range(Axis $axis, Level $floor, Level $ceiling, int $missingSample): AxisVerdict
    {
        return new AxisVerdict(
            axis: $axis,
            level: $floor,
            confidence: new Range($floor, $ceiling, $missingSample),
            evidences: [],
        );
    }
}
