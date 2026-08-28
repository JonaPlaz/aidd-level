<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Axis\Intervention;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Intervention\InterventionEvaluator;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\ProfileIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterventionEvaluatorTest extends TestCase
{
    /**
     * The four supplied profiles (docs/calibration.md): 4, 2, 0 (n=71), 1 → Red, Blue,
     * Silver, Copper, all confirmed since every sample is far above both floors.
     *
     * @return iterable<string, array{float, int, Level}>
     */
    public static function suppliedProfiles(): iterable
    {
        yield 'perceval: après coup, sur la majorité' => [4.0, 63, Level::Red];
        yield 'bohort: après coup, sur une partie' => [2.0, 48, Level::Blue];
        yield 'leodagan: jamais, échantillon au-dessus du plancher absence' => [0.0, 71, Level::Silver];
        yield 'arthur: aux étapes clés' => [1.0, 154, Level::Copper];
    }

    #[Test]
    #[DataProvider('suppliedProfiles')]
    public function pinsEachSuppliedProfileToItsCalibratedLevel(float $median, int $total, Level $expected): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile($median, $total));

        self::assertSame($expected, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
        self::assertSame(Axis::Intervention, $verdict->axis);
    }

    #[Test]
    public function aZeroMedianBelowTheAbsenceFloorIsARangeWithTheMissingSampleCounted(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(0.0, 8));

        self::assertSame(Level::Copper, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::Copper, $verdict->confidence->floor);
        self::assertSame(Level::Silver, $verdict->confidence->ceiling);
        self::assertSame(4, $verdict->confidence->missingSample);
    }

    #[Test]
    public function aZeroMedianAtOrAboveTheAbsenceFloorIsConfirmedSilver(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(0.0, 30));

        self::assertSame(Level::Silver, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
    }

    #[Test]
    public function aTotalExactlyAtTheAbsenceFloorIsConfirmedSilver(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(0.0, 12));

        self::assertSame(Level::Silver, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
    }

    #[Test]
    public function aTotalOneBelowTheAbsenceFloorIsARange(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(0.0, 11));

        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::Copper, $verdict->confidence->floor);
        self::assertSame(Level::Silver, $verdict->confidence->ceiling);
        self::assertSame(1, $verdict->confidence->missingSample);
    }

    #[Test]
    public function aTotalExactlyAtTheGeneralFloorIsConfirmed(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(2.0, 5));

        self::assertSame(Level::Blue, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
    }

    #[Test]
    public function aTotalOneBelowTheGeneralFloorIsARange(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(2.0, 4));

        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::Blue, $verdict->confidence->floor);
        self::assertSame(Level::Silver, $verdict->confidence->ceiling);
        self::assertSame(1, $verdict->confidence->missingSample);
    }

    #[Test]
    public function anEvenSampleFractionalMedianReadsAsKeySteps(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(1.5, 20));

        self::assertSame(Level::Copper, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
    }

    #[Test]
    public function aSampleBelowTheGeneralFloorIsARangeUpToSilver(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(2.0, 3));

        self::assertSame(Level::Blue, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::Blue, $verdict->confidence->floor);
        self::assertSame(Level::Silver, $verdict->confidence->ceiling);
        self::assertSame(2, $verdict->confidence->missingSample);
    }

    #[Test]
    public function aNullSignalIsARangeFromWhiteToSilverWithNoMissingSampleAndANote(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(null, 48));

        self::assertSame(Level::White, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::White, $verdict->confidence->floor);
        self::assertSame(Level::Silver, $verdict->confidence->ceiling);
        // docs/specs/05-robustesse.md § Signal absent: what is missing is the field itself,
        // not a number of pull requests.
        self::assertSame(0, $verdict->confidence->missingSample);
        self::assertSame([], $verdict->evidences);

        $absentSignalNotes = array_filter(
            $verdict->notes,
            static fn (Note $note): bool => str_contains($note->text, 'aucun signal de commits correctifs'),
        );
        self::assertCount(1, $absentSignalNotes);
        $note = array_values($absentSignalNotes)[0];
        self::assertSame('git-activity.json', $note->pointer->file);
        self::assertSame('pull_requests.median_correction_commits_after_open', $note->pointer->field);
        self::assertSame('absent', $note->pointer->value);
    }

    #[Test]
    public function everyVerdictCarriesTheSilverCeilingNote(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(1.0, 154));

        $ceilingNotes = array_filter(
            $verdict->notes,
            static fn (Note $note): bool => str_contains(
                $note->text,
                'Gold sur cet axe demanderait la preuve que le cadrage lui-même est automatisé',
            ),
        );

        self::assertNotEmpty($ceilingNotes);
        foreach ($ceilingNotes as $note) {
            self::assertSame('git-activity.json', $note->pointer->file);
        }
    }

    #[Test]
    public function aPresentMergedWithoutHumanEditFieldIsRenderedAsACorroboratingNoteOnly(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(2.0, 48, mergedWithoutHumanEdit: 10));

        $corroborationNotes = array_filter(
            $verdict->notes,
            static fn (Note $note): bool => str_contains($note->text, 'corrobore, ne décide pas'),
        );

        self::assertCount(1, $corroborationNotes);
        $note = array_values($corroborationNotes)[0];
        self::assertStringContainsString('10/48', $note->text);
        // The pointer carries the raw field value (10), never the derived ratio.
        self::assertSame('10', $note->pointer->value);
        self::assertSame('pull_requests.merged_without_human_edit_after_open', $note->pointer->field);
        self::assertSame(Level::Blue, $verdict->level, 'the corroborating field must not move the level');
    }

    #[Test]
    public function anAbsentMergedWithoutHumanEditFieldAddsNoCorroboratingNote(): void
    {
        $verdict = new InterventionEvaluator()->evaluate($this->profile(2.0, 48));

        $corroborationNotes = array_filter(
            $verdict->notes,
            static fn (Note $note): bool => str_contains($note->text, 'corrobore, ne décide pas'),
        );

        self::assertSame([], $corroborationNotes);
    }

    private function profile(
        ?float $median,
        ?int $total,
        ?int $mergedWithoutHumanEdit = null,
    ): Profile {
        return new Profile(
            identity: new ProfileIdentity('fixture', 'developer', [], []),
            gitActivity: new GitActivity(
                period: null,
                pullRequestsTotal: $total,
                medianFilesChanged: null,
                medianLinesChanged: null,
                medianCorrectionCommitsAfterOpen: $median,
                mergedWithoutHumanEditAfterOpen: $mergedWithoutHumanEdit,
                aiCoauthoredRatio: null,
                maxConcurrentBranches: null,
                medianConcurrentBranches: null,
                contextFiles: null,
            ),
        );
    }
}
