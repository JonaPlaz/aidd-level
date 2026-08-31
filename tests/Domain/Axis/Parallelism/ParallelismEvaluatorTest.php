<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Axis\Parallelism;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Parallelism\ParallelismEvaluator;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\ProfileIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParallelismEvaluatorTest extends TestCase
{
    private const int ABOVE_FLOOR_PR_TOTAL = 48; // above SampleFloors::PARALLELISM_MIN_PR (5)

    /**
     * The four supplied profiles (docs/calibration.md): 1, 1, 1, 4 → Green, Green, Green, Gold.
     *
     * @return iterable<string, array{?float, ?int, Level}>
     */
    public static function suppliedProfiles(): iterable
    {
        yield 'perceval' => [1.0, 2, Level::Green];
        yield 'bohort — the trap: max 3, the exact Copper cell, median 1' => [1.0, 3, Level::Green];
        yield 'leodagan' => [1.0, 2, Level::Green];
        yield 'arthur' => [4.0, 7, Level::Gold];
    }

    #[Test]
    #[DataProvider('suppliedProfiles')]
    public function pinsEachSuppliedProfile(?float $median, ?int $max, Level $expected): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile($median, $max, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Axis::Parallelism, $verdict->axis);
        self::assertSame($expected, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
    }

    #[Test]
    public function medianThreeIsGold(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(3.0, 3, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::Gold, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
    }

    #[Test]
    public function bohortTrapKeepsGreenAndNotesThePeakBecauseMaxNeverDecides(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(1.0, 3, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::Green, $verdict->level);
        self::assertCount(1, $verdict->notes);
        self::assertSame('pic observé : max 3, non retenu', $verdict->notes[0]->text);
        self::assertSame(
            'git-activity.json › parallelism.max_concurrent_branches = 3',
            (string) $verdict->notes[0]->pointer,
        );
    }

    #[Test]
    public function medianOneMaxSixStaysGreenAndNotesThePeak(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(1.0, 6, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::Green, $verdict->level);
        self::assertCount(1, $verdict->notes);
        self::assertSame('pic observé : max 6, non retenu', $verdict->notes[0]->text);
        self::assertSame(
            'git-activity.json › parallelism.max_concurrent_branches = 6',
            (string) $verdict->notes[0]->pointer,
        );
    }

    #[Test]
    public function medianZeroIsWhite(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(0.0, 0, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::White, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
        self::assertSame([], $verdict->notes);
    }

    #[Test]
    public function maxEqualToMedianIsNotAPeak(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(2.0, 2, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame([], $verdict->notes);
    }

    #[Test]
    public function totalBelowFloorYieldsARangeUpToGoldWithTheMissingCount(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(1.0, 1, 2));

        self::assertSame(Level::Green, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::Green, $verdict->confidence->floor);
        self::assertSame(Level::Gold, $verdict->confidence->ceiling);
        self::assertSame(3, $verdict->confidence->missingSample);
    }

    #[Test]
    public function totalBelowFloorAddsANoteWithTheShortageAndItsPointer(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(1.0, 1, 2));

        self::assertCount(1, $verdict->notes);
        self::assertSame(
            'échantillon insuffisant, plancher 5 : 3 PR manquantes',
            $verdict->notes[0]->text,
        );
        self::assertSame(
            'git-activity.json › pull_requests.total = 2',
            (string) $verdict->notes[0]->pointer,
        );
    }

    #[Test]
    public function greenClaimForExactlyOneReadsOneLaneAtATime(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(1.0, 1, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame('un chantier à la fois : sous le seuil de 3 de Copper.', $verdict->evidences[0]->claim);
    }

    #[Test]
    public function greenClaimForAMedianAboveOneNamesTheObservedValue(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(2.0, 2, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(
            '2 chantiers de front en médiane, sous le seuil de 3 de Copper.',
            $verdict->evidences[0]->claim,
        );
    }

    #[Test]
    public function evenSampleMedianHalfIsGreen(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(0.5, 1, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::Green, $verdict->level);
    }

    #[Test]
    public function evenSampleMedianTwoPointFiveIsGreen(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(2.5, 3, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::Green, $verdict->level);
    }

    #[Test]
    public function nullMedianIsNotObservableAndRangesFromWhiteToGold(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(null, null, self::ABOVE_FLOOR_PR_TOTAL));

        self::assertSame(Level::White, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::White, $verdict->confidence->floor);
        self::assertSame(Level::Gold, $verdict->confidence->ceiling);
        self::assertSame(0, $verdict->confidence->missingSample);
        self::assertSame([], $verdict->evidences);
        self::assertCount(1, $verdict->notes);
        self::assertSame(
            'médiane absente : fournir parallelism.median_concurrent_branches',
            $verdict->notes[0]->text,
        );
    }

    #[Test]
    public function everyEvidenceAndNoteCarriesAPointer(): void
    {
        $verdict = new ParallelismEvaluator()->evaluate(self::profile(1.0, 3, self::ABOVE_FLOOR_PR_TOTAL));

        foreach ($verdict->evidences as $evidence) {
            self::assertStringContainsString(' › ', (string) $evidence->pointer);
        }
        foreach ($verdict->notes as $note) {
            self::assertStringContainsString(' › ', (string) $note->pointer);
        }
    }

    private static function profile(?float $medianConcurrentBranches, ?int $maxConcurrentBranches, ?int $pullRequestsTotal): Profile
    {
        return new Profile(
            identity: new ProfileIdentity(id: 'test', role: 'developer', stack: [], available: ['git-activity.json']),
            gitActivity: new GitActivity(
                period: null,
                pullRequestsTotal: $pullRequestsTotal,
                medianFilesChanged: null,
                medianLinesChanged: null,
                medianCorrectionCommitsAfterOpen: null,
                mergedWithoutHumanEditAfterOpen: null,
                aiCoauthoredRatio: null,
                maxConcurrentBranches: $maxConcurrentBranches,
                medianConcurrentBranches: $medianConcurrentBranches,
                contextFiles: null,
            ),
        );
    }
}
