<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Application;

use AiddLevel\Application\EvaluateProfile;
use AiddLevel\Application\EvaluateProfileHandler;
use AiddLevel\Domain\Assessment;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Harness\HarnessEvaluator;
use AiddLevel\Domain\Axis\Intervention\InterventionEvaluator;
use AiddLevel\Domain\Axis\Parallelism\ParallelismEvaluator;
use AiddLevel\Domain\Axis\Size\SizeEvaluator;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Exception\ProfileNotAssessable;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Profile\ContextFiles;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Period;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\ProfileIdentity;
use AiddLevel\Domain\Progression\RecommendationPolicy;
use AiddLevel\Infrastructure\Profile\DirectoryProfileSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EvaluateProfileHandler (docs/specs/00-vue-ensemble.md § 4.2, docs/specs/05-robustesse.md):
 * gate → White filter → evaluators → LevelRule → Assessment, never an exception. The four
 * real profiles fix the calibrated levels (docs/calibration.md); in-memory fixtures cover the
 * degraded cases the four real profiles never exercise.
 */
final class EvaluateProfileHandlerTest extends TestCase
{
    private const string PROFILES_DIR = __DIR__.'/../../profiles';

    /**
     * @param list<Axis> $expectedCappingAxes
     */
    #[Test]
    #[DataProvider('calibratedProfiles')]
    public function matchesTheCalibratedLevelAndCappingAxes(string $profile, Level $expectedLevel, array $expectedCappingAxes): void
    {
        $handler = $this->handlerWithRealSource();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/'.$profile));

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame($expectedLevel, $assessment->level);
        self::assertSame($expectedLevel, $assessment->ceiling);
        self::assertEqualsCanonicalizing($expectedCappingAxes, $assessment->cappingAxes);
    }

    /**
     * @return iterable<string, array{0: string, 1: Level, 2: list<Axis>}>
     */
    public static function calibratedProfiles(): iterable
    {
        yield 'perceval -> Red, Taille+Harness+Intervention ex æquo' => [
            'perceval', Level::Red, [Axis::Size, Axis::Harness, Axis::Intervention],
        ];
        yield 'bohort -> Blue, Taille+Harness+Intervention ex æquo' => [
            'bohort', Level::Blue, [Axis::Size, Axis::Harness, Axis::Intervention],
        ];
        yield 'leodagan -> Green, En parallèle seul' => [
            'leodagan', Level::Green, [Axis::Parallelism],
        ];
        yield 'arthur -> Copper, Harness+Intervention ex æquo' => [
            'arthur', Level::Copper, [Axis::Harness, Axis::Intervention],
        ];
    }

    #[Test]
    public function aNonExistentDirectoryIsNotAssessableWithoutException(): void
    {
        $handler = $this->handlerWithRealSource();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/does-not-exist'));

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertNotNull($assessment->missingPrerequisite);
        self::assertNotSame('', $assessment->missingPrerequisite);
        self::assertNull($assessment->identity);
    }

    #[Test]
    public function aCorruptedProfileJsonIsNotAssessableWithoutException(): void
    {
        $sandbox = $this->sandbox();
        file_put_contents($sandbox.'/profile.json', '{not json');

        $handler = $this->handlerWithRealSource();
        $assessment = $handler->handle(new EvaluateProfile($sandbox));

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertStringContainsString('profile.json', (string) $assessment->missingPrerequisite);

        $this->cleanup($sandbox);
    }

    #[Test]
    public function aMissingGitActivityJsonIsNotAssessableAndKeepsThePartialIdentity(): void
    {
        $sandbox = $this->sandbox();
        file_put_contents($sandbox.'/profile.json', json_encode(['profile_id' => 'ghost']));

        $handler = $this->handlerWithRealSource();
        $assessment = $handler->handle(new EvaluateProfile($sandbox));

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertStringContainsString('git-activity.json', (string) $assessment->missingPrerequisite);
        self::assertNotNull($assessment->missingPrerequisite);
        self::assertNotNull($assessment->hint);
        self::assertNotNull($assessment->identity);
        self::assertSame('ghost', $assessment->identity->id);
        self::assertNotEmpty($assessment->notes);

        $this->cleanup($sandbox);
    }

    #[Test]
    public function aZeroPullRequestTotalIsNotAssessableWithoutException(): void
    {
        $sandbox = $this->sandbox();
        file_put_contents($sandbox.'/profile.json', json_encode(['profile_id' => 'ghost']));
        file_put_contents($sandbox.'/git-activity.json', json_encode(['pull_requests' => ['total' => 0]]));

        $handler = $this->handlerWithRealSource();
        $assessment = $handler->handle(new EvaluateProfile($sandbox));

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertStringContainsString('total = 0', (string) $assessment->missingPrerequisite);

        $this->cleanup($sandbox);
    }

    #[Test]
    public function aZeroRatioAndEmptyContextFiltersToWhiteOnEveryAxisWithoutCallingEvaluators(): void
    {
        $profile = $this->profileWith(new GitActivity(
            period: null,
            pullRequestsTotal: 20,
            medianFilesChanged: 10.0,
            medianLinesChanged: 200.0,
            medianCorrectionCommitsAfterOpen: 1.0,
            mergedWithoutHumanEditAfterOpen: 5,
            aiCoauthoredRatio: 0.0,
            maxConcurrentBranches: 2,
            medianConcurrentBranches: 1.0,
            contextFiles: new ContextFiles(agentsMd: false, rules: 0, skills: 0, hooks: 0, agents: 0),
        ));

        $handler = new EvaluateProfileHandler(
            new InMemoryProfileSource($profile),
            $this->realEvaluatorsThatMustNotBeCalled(),
            new RecommendationPolicy(),
        );

        $assessment = $handler->handle(new EvaluateProfile('unused'));

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::White, $assessment->level);
        self::assertCount(4, $assessment->cappingAxes);
        foreach ($assessment->verdicts as $verdict) {
            self::assertSame(Level::White, $verdict->level);
        }
    }

    #[Test]
    public function aRatioAboveZeroIsNeverWhiteFiltered(): void
    {
        // docs/specs/05-robustesse.md § Filtre White: "perceval à 0,04 n'est pas White".
        $handler = $this->handlerWithRealSource();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/perceval'));

        foreach ($assessment->verdicts as $verdict) {
            if (Axis::Harness === $verdict->axis) {
                self::assertNotSame(Level::White, $verdict->level);
            }
        }
    }

    #[Test]
    public function aShortSampleOfThreePullRequestsIsLowConfidence(): void
    {
        $profile = $this->profileWith(new GitActivity(
            period: null,
            pullRequestsTotal: 3,
            medianFilesChanged: 10.0,
            medianLinesChanged: 200.0,
            medianCorrectionCommitsAfterOpen: 2.0,
            mergedWithoutHumanEditAfterOpen: 1,
            aiCoauthoredRatio: 0.5,
            maxConcurrentBranches: 1,
            medianConcurrentBranches: 1.0,
            contextFiles: new ContextFiles(agentsMd: true, rules: 1, skills: 0, hooks: 0, agents: 0),
        ));

        $handler = $this->handlerWithFixtureSource($profile);
        $assessment = $handler->handle(new EvaluateProfile('unused'));

        self::assertSame(AssessmentStatus::LowConfidence, $assessment->status);
    }

    #[Test]
    public function absentSizeSignalsAreLowConfidenceWithAFirstRecommendationToProvideTheField(): void
    {
        $profile = $this->profileWith(new GitActivity(
            period: null,
            pullRequestsTotal: 20,
            medianFilesChanged: null,
            medianLinesChanged: null,
            medianCorrectionCommitsAfterOpen: 2.0,
            mergedWithoutHumanEditAfterOpen: 1,
            aiCoauthoredRatio: 0.5,
            maxConcurrentBranches: 1,
            medianConcurrentBranches: 1.0,
            contextFiles: new ContextFiles(agentsMd: true, rules: 1, skills: 0, hooks: 0, agents: 0),
        ));

        $handler = $this->handlerWithFixtureSource($profile);
        $assessment = $handler->handle(new EvaluateProfile('unused'));

        self::assertSame(AssessmentStatus::LowConfidence, $assessment->status);
        self::assertContains(Axis::Size, $assessment->cappingAxes);
        self::assertNotEmpty($assessment->recommendations);
        self::assertStringStartsWith('fournir', $assessment->recommendations[0]->gesture);
    }

    #[Test]
    public function anAnnouncedButMissingPieceIsNoted(): void
    {
        $identity = new ProfileIdentity('galahad', 'stagiaire', [], ['session.md']);
        $profile = new Profile(
            identity: $identity,
            gitActivity: $this->defaultGitActivity(),
            presentPieces: [],
        );

        $handler = $this->handlerWithFixtureSource($profile);
        $assessment = $handler->handle(new EvaluateProfile('unused'));

        $noteTexts = array_map(static fn ($note): string => $note->text, $assessment->notes);
        self::assertContains('pièce annoncée, absente : session.md', $noteTexts);
    }

    #[Test]
    public function aBlankProfileIdNeverBuildsABlankPointerAndIsNotedInstead(): void
    {
        // Codex review of PR #24, remark 1: `profile_id` present but blank in profile.json.
        $sandbox = $this->sandbox();
        file_put_contents($sandbox.'/profile.json', json_encode(['profile_id' => '']));

        $handler = $this->handlerWithRealSource();
        $assessment = $handler->handle(new EvaluateProfile($sandbox));

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertNotNull($assessment->identity);
        self::assertSame('', $assessment->identity->id);
        $noteTexts = array_map(static fn ($note): string => $note->text, $assessment->notes);
        self::assertContains('profile_id absent', $noteTexts);

        $this->cleanup($sandbox);
    }

    #[Test]
    public function anAbsentContextFilesBlockIsNeverWhiteFiltered(): void
    {
        // Codex review of PR #24, remark 2: the filter only fires once context_files was
        // actually read and confirms nothing is there — an absent block falls through to
        // HarnessEvaluator, which renders its own non-observable Range.
        $profile = $this->profileWith(new GitActivity(
            period: null,
            pullRequestsTotal: 20,
            medianFilesChanged: 10.0,
            medianLinesChanged: 200.0,
            medianCorrectionCommitsAfterOpen: 1.0,
            mergedWithoutHumanEditAfterOpen: 5,
            aiCoauthoredRatio: 0.0,
            maxConcurrentBranches: 2,
            medianConcurrentBranches: 1.0,
            contextFiles: null,
        ));

        $handler = $this->handlerWithFixtureSource($profile);
        $assessment = $handler->handle(new EvaluateProfile('unused'));

        self::assertSame(AssessmentStatus::LowConfidence, $assessment->status);

        $harness = null;
        foreach ($assessment->verdicts as $verdict) {
            if (Axis::Harness === $verdict->axis) {
                $harness = $verdict;
            }
        }

        self::assertNotNull($harness);
        self::assertInstanceOf(Range::class, $harness->confidence);
    }

    #[Test]
    public function axisVerdictNotesReachTheAssessmentPrefixedByTheirAxis(): void
    {
        // Codex review of PR #24, remark 3: bohort's Parallelism axis carries a "peak
        // observed, not retained" note (max_concurrent_branches = 3, median = 1) that the
        // renderer can only ever show via Assessment::$notes.
        $handler = $this->handlerWithRealSource();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/bohort'));

        $noteTexts = array_map(static fn ($note): string => $note->text, $assessment->notes);
        $matches = array_filter(
            $noteTexts,
            static fn (string $text): bool => str_starts_with($text, 'En parallèle : pic observé'),
        );

        self::assertNotEmpty($matches);
    }

    #[Test]
    public function theMissingPrerequisiteAndHintSplitOnBytesNotCharacters(): void
    {
        // Codex review of PR #24, remark 4: the em dash separator is multi-byte, strpos()
        // returns a byte offset — mb_strlen() on the separator sliced the hint two bytes
        // short. Asserted against the real, unmodified DirectoryProfileSource message.
        $handler = $this->handlerWithRealSource();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/does-not-exist'));

        self::assertSame(
            self::PROFILES_DIR."/does-not-exist n'est pas un dossier lisible",
            $assessment->missingPrerequisite,
        );
        self::assertSame('fournir un chemin de dossier de profil existant', $assessment->hint);
    }

    #[Test]
    public function signalAbsentRecommendationsComeBeforeOrdinaryGesturesEvenExAequo(): void
    {
        // Codex review of PR #24, remark 5: Taille's median is absent (signal absent) while
        // Parallelism sits at a genuine, measured White (median = 0, not absent) — both cap
        // the level at White, ex æquo, but the "fournir" recommendation must still lead.
        $profile = $this->profileWith(new GitActivity(
            period: null,
            pullRequestsTotal: 20,
            medianFilesChanged: null,
            medianLinesChanged: null,
            medianCorrectionCommitsAfterOpen: 1.0,
            mergedWithoutHumanEditAfterOpen: 1,
            aiCoauthoredRatio: 0.5,
            maxConcurrentBranches: 0,
            medianConcurrentBranches: 0.0,
            contextFiles: new ContextFiles(agentsMd: true, rules: 1, skills: 0, hooks: 0, agents: 0),
        ));

        $handler = $this->handlerWithFixtureSource($profile);
        $assessment = $handler->handle(new EvaluateProfile('unused'));

        self::assertContains(Axis::Size, $assessment->cappingAxes);
        self::assertContains(Axis::Parallelism, $assessment->cappingAxes);
        self::assertNotEmpty($assessment->recommendations);
        self::assertSame(Axis::Size, $assessment->recommendations[0]->axis);
        self::assertStringStartsWith('fournir le champ', $assessment->recommendations[0]->gesture);
    }

    private function handlerWithRealSource(): EvaluateProfileHandler
    {
        return new EvaluateProfileHandler(
            new DirectoryProfileSource(),
            $this->realEvaluators(),
            new RecommendationPolicy(),
        );
    }

    private function handlerWithFixtureSource(Profile $profile): EvaluateProfileHandler
    {
        return new EvaluateProfileHandler(
            new InMemoryProfileSource($profile),
            $this->realEvaluators(),
            new RecommendationPolicy(),
        );
    }

    /**
     * @return list<AxisEvaluator>
     */
    private function realEvaluators(): array
    {
        return [
            new SizeEvaluator(),
            new HarnessEvaluator(),
            new InterventionEvaluator(),
            new ParallelismEvaluator(),
        ];
    }

    /**
     * Same evaluators, only used to prove the White filter never calls a single one of them
     * (docs/specs/05-robustesse.md § Filtre White: "sans calcul") — wrapped so a call would be
     * observable if it happened, without needing a mocking framework.
     *
     * @return list<AxisEvaluator>
     */
    private function realEvaluatorsThatMustNotBeCalled(): array
    {
        return array_map(
            static fn (AxisEvaluator $inner): AxisEvaluator => new class($inner) implements AxisEvaluator {
                public function __construct(private readonly AxisEvaluator $inner)
                {
                }

                public function axis(): Axis
                {
                    return $this->inner->axis();
                }

                public function evaluate(Profile $profile): never
                {
                    throw new \LogicException('The White filter must not call an AxisEvaluator.');
                }
            },
            $this->realEvaluators(),
        );
    }

    private function profileWith(GitActivity $gitActivity): Profile
    {
        return new Profile(
            identity: new ProfileIdentity('fixture', 'role', [], []),
            gitActivity: $gitActivity,
        );
    }

    private function defaultGitActivity(): GitActivity
    {
        return new GitActivity(
            period: new Period('2026-01-01', '2026-06-01'),
            pullRequestsTotal: 20,
            medianFilesChanged: 10.0,
            medianLinesChanged: 200.0,
            medianCorrectionCommitsAfterOpen: 1.0,
            mergedWithoutHumanEditAfterOpen: 5,
            aiCoauthoredRatio: 0.5,
            maxConcurrentBranches: 2,
            medianConcurrentBranches: 1.0,
            contextFiles: new ContextFiles(agentsMd: true, rules: 1, skills: 0, hooks: 0, agents: 0),
        );
    }

    private function sandbox(): string
    {
        $path = sys_get_temp_dir().'/aidd-level-handler-'.bin2hex(random_bytes(8));
        mkdir($path, recursive: true);

        return $path;
    }

    private function cleanup(string $path): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            \assert($item instanceof \SplFileInfo);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
