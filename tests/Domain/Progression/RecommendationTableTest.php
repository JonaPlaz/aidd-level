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

        self::assertStringContainsString('fichier mémoire', $toBlue);
        self::assertSame($toGreen, $toCopper);
        self::assertStringContainsString('hook', $toCopper);
        self::assertSame($toSilver, $toGold);
        self::assertStringContainsString('relance automatique bornée', $toSilver);
    }

    #[Test]
    public function parallelismOnlyHasAGestureFromCopperUp(): void
    {
        $table = new RecommendationTable();

        self::assertStringContainsString('chantier', $table->gestureFor(Axis::Parallelism, Level::Copper));
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

        self::assertStringContainsString('ce qui est attendu', $table->gestureFor(Axis::Intervention, Level::Blue));
        self::assertSame(
            $table->gestureFor(Axis::Intervention, Level::Green),
            $table->gestureFor(Axis::Intervention, Level::Copper),
        );
        self::assertStringContainsString('automatiser la validation', $table->gestureFor(Axis::Intervention, Level::Silver));
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
            self::assertSame(
                'ne rien décréter : la taille habituelle monte quand le dispositif tient ; voir Harness',
                $table->gestureFor(Axis::Size, $level),
            );
        }
    }
}
