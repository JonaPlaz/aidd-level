<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Progression;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Progression\RecommendationTable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecommendationTableTest extends TestCase
{
    #[Test]
    public function harnessGestureVariesByTargetLevelBucket(): void
    {
        $table = new RecommendationTable();

        $toBlue = $table->gestureFor(Axis::Harness, Level::Blue);
        $toCopper = $table->gestureFor(Axis::Harness, Level::Copper);
        $toGreen = $table->gestureFor(Axis::Harness, Level::Green);
        $toSilver = $table->gestureFor(Axis::Harness, Level::Silver);
        $toGold = $table->gestureFor(Axis::Harness, Level::Gold);

        self::assertStringContainsString('memory file', $toBlue);
        self::assertSame($toGreen, $toCopper);
        self::assertStringContainsString('hook', $toCopper);
        self::assertSame($toSilver, $toGold);
        self::assertStringContainsString('bounded automatic retry', $toSilver);
    }

    #[Test]
    public function parallelismOnlyHasAGestureFromCopperUp(): void
    {
        $table = new RecommendationTable();

        self::assertStringContainsString('workstream', $table->gestureFor(Axis::Parallelism, Level::Copper));
        self::assertSame(
            $table->gestureFor(Axis::Parallelism, Level::Copper),
            $table->gestureFor(Axis::Parallelism, Level::Silver),
        );

        $this->expectException(\InvalidArgumentException::class);
        $table->gestureFor(Axis::Parallelism, Level::Blue);
    }

    #[Test]
    public function interventionGestureVariesByTargetLevelBucket(): void
    {
        $table = new RecommendationTable();

        self::assertStringContainsString('expected', $table->gestureFor(Axis::Intervention, Level::Blue));
        self::assertSame(
            $table->gestureFor(Axis::Intervention, Level::Green),
            $table->gestureFor(Axis::Intervention, Level::Copper),
        );
        self::assertStringContainsString('automate validation', $table->gestureFor(Axis::Intervention, Level::Silver));
    }

    #[Test]
    public function interventionHasNoGestureTowardsGoldBecauseTheAxisPlateausAtSilver(): void
    {
        $table = new RecommendationTable();

        $this->expectException(\InvalidArgumentException::class);
        $table->gestureFor(Axis::Intervention, Level::Gold);
    }

    #[Test]
    public function sizeNeverGetsItsOwnGestureItRedirectsToHarnessAtEveryLevel(): void
    {
        $table = new RecommendationTable();

        foreach (Level::cases() as $level) {
            self::assertSame('usual size follows the setup; see Harness', $table->gestureFor(Axis::Size, $level));
        }
    }
}
