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

        $toRed = $table->gestureFor(Axis::Harness, Level::Red);
        $toBlue = $table->gestureFor(Axis::Harness, Level::Blue);
        $toCopper = $table->gestureFor(Axis::Harness, Level::Copper);
        $toGreen = $table->gestureFor(Axis::Harness, Level::Green);
        $toSilver = $table->gestureFor(Axis::Harness, Level::Silver);
        $toGold = $table->gestureFor(Axis::Harness, Level::Gold);

        self::assertStringContainsString('signer ses commits', $toRed);
        self::assertStringContainsString('fichier mémoire', $toBlue);
        self::assertSame($toGreen, $toCopper);
        self::assertStringContainsString('hook', $toCopper);
        self::assertSame($toSilver, $toGold);
        self::assertStringContainsString('relance automatique bornée', $toSilver);
    }

    #[Test]
    public function parallelismHasTwoBucketsAndNeverThrows(): void
    {
        $table = new RecommendationTable();

        self::assertStringContainsString('mener un chantier', $table->gestureFor(Axis::Parallelism, Level::Red));
        self::assertSame(
            $table->gestureFor(Axis::Parallelism, Level::Red),
            $table->gestureFor(Axis::Parallelism, Level::Green),
        );

        self::assertStringContainsString('chantier', $table->gestureFor(Axis::Parallelism, Level::Copper));
        self::assertSame(
            $table->gestureFor(Axis::Parallelism, Level::Copper),
            $table->gestureFor(Axis::Parallelism, Level::Silver),
        );
    }

    #[Test]
    public function interventionGestureVariesByTargetLevelBucket(): void
    {
        $table = new RecommendationTable();

        self::assertStringContainsString('même geste que Harness', $table->gestureFor(Axis::Intervention, Level::Red));
        self::assertStringContainsString('ce qui est attendu', $table->gestureFor(Axis::Intervention, Level::Blue));
        self::assertSame(
            $table->gestureFor(Axis::Intervention, Level::Green),
            $table->gestureFor(Axis::Intervention, Level::Copper),
        );
        self::assertStringContainsString('automatiser la validation', $table->gestureFor(Axis::Intervention, Level::Silver));
    }

    #[Test]
    public function interventionTowardsGoldNamesThePlateauInsteadOfInventingATask(): void
    {
        $table = new RecommendationTable();

        self::assertSame(
            "aucun geste : l'axe plafonne à Silver par construction — « cadrage compris » "
            .'n\'est pas observable dans les pièces fournies',
            $table->gestureFor(Axis::Intervention, Level::Gold),
        );
    }

    #[Test]
    public function sizeNeverGetsItsOwnGestureItRedirectsToHarnessAtEveryLevel(): void
    {
        $table = new RecommendationTable();

        foreach (Level::cases() as $level) {
            self::assertSame(
                'ne rien décréter : la taille habituelle monte quand le dispositif tient ; geste renvoyé à Harness',
                $table->gestureFor(Axis::Size, $level),
            );
        }
    }

    #[Test]
    public function proofFieldIsBucketedForHarnessAndFixedForTheOtherAxes(): void
    {
        $table = new RecommendationTable();

        self::assertSame('commits.ai_coauthored_ratio', $table->proofFieldFor(Axis::Harness, Level::Red));
        self::assertSame('context_files.agents_md', $table->proofFieldFor(Axis::Harness, Level::Blue));
        self::assertSame(
            'context_files.rules_count, skills_count, hooks_count, agents_count',
            $table->proofFieldFor(Axis::Harness, Level::Copper),
        );
        self::assertSame('repo-context/ › bounded retry', $table->proofFieldFor(Axis::Harness, Level::Silver));

        self::assertSame(
            'parallelism.median_concurrent_branches',
            $table->proofFieldFor(Axis::Parallelism, Level::Gold),
        );
        self::assertSame(
            'pull_requests.median_correction_commits_after_open',
            $table->proofFieldFor(Axis::Intervention, Level::Gold),
        );
        self::assertSame('pull_requests.median_files_changed', $table->proofFieldFor(Axis::Size, Level::Blue));
    }
}
