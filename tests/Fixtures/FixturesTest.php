<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Fixtures;

use AiddLevel\Application\EvaluateProfile;
use AiddLevel\Application\EvaluateProfileHandler;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Harness\HarnessEvaluator;
use AiddLevel\Domain\Axis\Intervention\InterventionEvaluator;
use AiddLevel\Domain\Axis\Parallelism\ParallelismEvaluator;
use AiddLevel\Domain\Axis\Size\SizeEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Progression\RecommendationPolicy;
use AiddLevel\Infrastructure\Profile\DirectoryProfileSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `fixtures/` (docs/specs/02–05, § « non prouvé » / « Ce que le jeu fourni ne valide pas »):
 * profils maison couvrant ce qu'aucun des quatre profils calibrés (docs/calibration.md) ne
 * met en scène — Silver, le plafond de Gold sur Intervention, White, un échantillon court, un
 * champ décideur absent, un cumul de la grille rompu, une boucle non observable, une
 * incohérence annoncé/présent, l'échec de gate à zéro PR, et la bordure exacte du plancher
 * « jamais ». Chaque niveau attendu ci-dessous est fixé **par construction** (jamais par un
 * tiers) : c'est le fichier JSON de la fixture qui décide, pas ce test.
 *
 * Assemblage identique à `EvaluateProfileHandlerTest` : un `DirectoryProfileSource` réel lit
 * le dossier, les quatre `AxisEvaluator` et `RecommendationPolicy` sont câblés à la main,
 * exactement comme le fait `EvaluateCommand` en production.
 */
final class FixturesTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__.'/../../fixtures';

    #[Test]
    public function silverLoopReachesSilverCappedByInterventionAlone(): void
    {
        $assessment = $this->evaluate('silver-loop');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::Silver, $assessment->level);
        self::assertSame(Level::Silver, $assessment->ceiling);
        self::assertEqualsCanonicalizing([Axis::Intervention], $assessment->cappingAxes);

        $harness = $this->verdictFor($assessment->verdicts, Axis::Harness);
        self::assertSame(Level::Gold, $harness->level);

        $intervention = $this->verdictFor($assessment->verdicts, Axis::Intervention);
        self::assertSame(Level::Silver, $intervention->level);
    }

    #[Test]
    public function loopFarApartCapsAtCopperOnHarnessAloneWithNoUnboundedRetryNote(): void
    {
        // docs/specs/02-axe-harness.md § « Boucles — détection resserrée » (chantier 14,
        // issue #45): `while` L3 and `budget` L210 in the same file, 207 lines apart — the
        // exact shape the issue complained about. The old, unbounded LoopDetector promoted
        // this to Gold; the proximity window closes it back to Copper.
        $assessment = $this->evaluate('loop-far-apart');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::Copper, $assessment->level);
        self::assertEqualsCanonicalizing([Axis::Harness], $assessment->cappingAxes);

        $harness = $this->verdictFor($assessment->verdicts, Axis::Harness);
        self::assertSame(Level::Copper, $harness->level);

        $noteTexts = array_map(static fn ($note): string => $note->text, $harness->notes);
        self::assertSame(['boucles : aucune relance bornée trouvée'], $noteTexts);
    }

    #[Test]
    public function goldUnreachableStaysAtSilverWithTheInterventionCeilingNote(): void
    {
        $assessment = $this->evaluate('gold-unreachable');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::Silver, $assessment->level);
        self::assertEqualsCanonicalizing([Axis::Intervention], $assessment->cappingAxes);

        self::assertSame(Level::Gold, $this->verdictFor($assessment->verdicts, Axis::Size)->level);
        self::assertSame(Level::Gold, $this->verdictFor($assessment->verdicts, Axis::Harness)->level);
        self::assertSame(Level::Gold, $this->verdictFor($assessment->verdicts, Axis::Parallelism)->level);

        $noteTexts = array_map(static fn ($note): string => $note->text, $assessment->notes);
        $ceilingNoted = array_filter(
            $noteTexts,
            static fn (string $text): bool => str_contains($text, 'cadrage lui-même est automatisé'),
        );
        self::assertNotEmpty($ceilingNoted, 'Gold sur Intervention doit être dit hors de portée par construction.');
    }

    #[Test]
    public function whiteFiltersTheFourAxesAtOnce(): void
    {
        $assessment = $this->evaluate('white');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::White, $assessment->level);
        self::assertCount(4, $assessment->cappingAxes);
        foreach ($assessment->verdicts as $verdict) {
            self::assertSame(Level::White, $verdict->level);
        }
    }

    #[Test]
    public function shortSampleOfThreePullRequestsIsLowConfidenceWithCountedRanges(): void
    {
        $assessment = $this->evaluate('short-sample');

        self::assertSame(AssessmentStatus::LowConfidence, $assessment->status);

        $intervention = $this->verdictFor($assessment->verdicts, Axis::Intervention);
        self::assertInstanceOf(Range::class, $intervention->confidence);
        self::assertSame(Level::Copper, $intervention->confidence->floor);
        self::assertSame(Level::Silver, $intervention->confidence->ceiling);
        self::assertSame(9, $intervention->confidence->missingSample);

        $parallelism = $this->verdictFor($assessment->verdicts, Axis::Parallelism);
        self::assertInstanceOf(Range::class, $parallelism->confidence);
        self::assertSame(2, $parallelism->confidence->missingSample);

        $size = $this->verdictFor($assessment->verdicts, Axis::Size);
        self::assertInstanceOf(Range::class, $size->confidence);
        self::assertSame(2, $size->confidence->missingSample);
    }

    #[Test]
    public function absentSignalsAreLowConfidenceWithFieldsToProvideLeadingTheRecommendations(): void
    {
        $assessment = $this->evaluate('absent-signals');

        self::assertSame(AssessmentStatus::LowConfidence, $assessment->status);
        self::assertContains(Axis::Size, $assessment->cappingAxes);
        self::assertContains(Axis::Intervention, $assessment->cappingAxes);
        self::assertContains(Axis::Parallelism, $assessment->cappingAxes);

        self::assertNotEmpty($assessment->recommendations);
        foreach ($assessment->recommendations as $recommendation) {
            self::assertStringStartsWith('fournir le champ', $recommendation->gesture);
        }
    }

    #[Test]
    public function countersWithoutMemoryStayAtPromptsWithTheCumulativeNote(): void
    {
        $assessment = $this->evaluate('counters-no-memory');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::Red, $assessment->level);
        self::assertEqualsCanonicalizing([Axis::Harness], $assessment->cappingAxes);

        $harness = $this->verdictFor($assessment->verdicts, Axis::Harness);
        self::assertSame(Level::Red, $harness->level);

        $noteTexts = array_map(static fn ($note): string => $note->text, $harness->notes);
        $cumulative = array_filter(
            $noteTexts,
            static fn (string $text): bool => str_contains($text, 'la grille cumule'),
        );
        self::assertNotEmpty($cumulative);
    }

    #[Test]
    public function noRepoContextCapsHarnessAtCopperWithANonObservableLoopNote(): void
    {
        $assessment = $this->evaluate('no-repo-context');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::Copper, $assessment->level);

        $harness = $this->verdictFor($assessment->verdicts, Axis::Harness);
        self::assertSame(Level::Copper, $harness->level);

        $noteTexts = array_map(static fn ($note): string => $note->text, $harness->notes);
        self::assertContains('boucles non observables : repo-context/ absent', $noteTexts);
    }

    #[Test]
    public function inconsistentAvailableNotesBothDirections(): void
    {
        $assessment = $this->evaluate('inconsistent-available');

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);

        $noteTexts = array_map(static fn ($note): string => $note->text, $assessment->notes);
        self::assertContains('pièce annoncée, absente : session.md', $noteTexts);
        self::assertContains('pièce présente, non annoncée : declaratif.md', $noteTexts);
    }

    #[Test]
    public function zeroPullRequestsIsNotAssessable(): void
    {
        $assessment = $this->evaluate('zero-prs');

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertNotNull($assessment->missingPrerequisite);
        self::assertStringContainsString('total = 0', $assessment->missingPrerequisite);
    }

    #[Test]
    public function medianZeroCorrectionOnEightPullRequestsIsACopperToSilverRangeWithFourMissing(): void
    {
        $assessment = $this->evaluate('median-zero-correction-short');

        self::assertSame(AssessmentStatus::LowConfidence, $assessment->status);

        $intervention = $this->verdictFor($assessment->verdicts, Axis::Intervention);
        self::assertInstanceOf(Range::class, $intervention->confidence);
        self::assertSame(Level::Copper, $intervention->confidence->floor);
        self::assertSame(Level::Silver, $intervention->confidence->ceiling);
        self::assertSame(4, $intervention->confidence->missingSample);
    }

    private function evaluate(string $fixture): \AiddLevel\Domain\Assessment
    {
        $handler = new EvaluateProfileHandler(
            new DirectoryProfileSource(),
            [
                new SizeEvaluator(),
                new HarnessEvaluator(),
                new InterventionEvaluator(),
                new ParallelismEvaluator(),
            ],
            new RecommendationPolicy(),
        );

        return $handler->handle(new EvaluateProfile(self::FIXTURES_DIR.'/'.$fixture));
    }

    /**
     * @param list<AxisVerdict> $verdicts
     */
    private function verdictFor(array $verdicts, Axis $axis): AxisVerdict
    {
        foreach ($verdicts as $verdict) {
            if ($axis === $verdict->axis) {
                return $verdict;
            }
        }

        self::fail(sprintf('No verdict found for axis %s.', $axis->name));
    }
}
