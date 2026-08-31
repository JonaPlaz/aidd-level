<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Axis\Size;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Size\SizeEvaluator;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\ProfileIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * docs/specs/01-axe-taille.md § Tests: the four supplied profiles, the files-then-lines
 * fallback, and the confidence Range below the pull-request sample floor.
 */
final class SizeEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, float, Level}>
     */
    public static function suppliedProfiles(): iterable
    {
        // pull_requests.total values from docs/calibration.md, all well above the floor of 5.
        yield 'perceval: 2 files → S/Red' => [63, 2.0, Level::Red];
        yield 'bohort: 7 files → M/Blue' => [48, 7.0, Level::Blue];
        yield 'leodagan: 13 files → L/Gold' => [71, 13.0, Level::Gold];
        yield 'arthur: 29 files → XL/Gold' => [154, 29.0, Level::Gold];
    }

    #[Test]
    #[DataProvider('suppliedProfiles')]
    public function pinsTheLevelOfEachSuppliedProfile(int $total, float $medianFiles, Level $expected): void
    {
        $profile = $this->profileWith(
            pullRequestsTotal: $total,
            medianFilesChanged: $medianFiles,
        );

        $verdict = new SizeEvaluator()->evaluate($profile);

        self::assertSame(Axis::Size, $verdict->axis);
        self::assertSame($expected, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
        self::assertNotEmpty($verdict->evidences);
        foreach ($verdict->evidences as $evidence) {
            self::assertStringContainsString(' › ', (string) $evidence->pointer);
        }
    }

    #[Test]
    public function evidenceCitesTheFilesPointerWithATrueMedian(): void
    {
        $profile = $this->profileWith(pullRequestsTotal: 71, medianFilesChanged: 13.0);

        $verdict = new SizeEvaluator()->evaluate($profile);

        self::assertCount(1, $verdict->evidences);
        $evidence = $verdict->evidences[0];
        self::assertSame('13 fichiers modifiés en médiane : bande L (≤ 20), satisfait de Green à Gold.', $evidence->claim);
        self::assertSame(
            'git-activity.json › pull_requests.median_files_changed = 13',
            (string) $evidence->pointer,
        );
    }

    #[Test]
    public function fallsBackToLinesWhenFileCountIsAbsent(): void
    {
        // docs/specs/01-axe-taille.md § Tests: files absent, lines = 300 → L band.
        $profile = $this->profileWith(
            pullRequestsTotal: 71,
            medianFilesChanged: null,
            medianLinesChanged: 300.0,
        );

        $verdict = new SizeEvaluator()->evaluate($profile);

        self::assertSame(Level::Gold, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
        $evidence = $verdict->evidences[0];
        self::assertSame(
            'git-activity.json › pull_requests.median_lines_changed = 300',
            (string) $evidence->pointer,
        );

        // Codex review of PR #17: one assertion per pointer — the files note and the lines
        // note are separate, each citing its own field.
        [$filesNote, $linesNote] = $verdict->notes;

        self::assertStringContainsString('repli sur les lignes', $filesNote->text);
        self::assertStringContainsString('median_files_changed absent', $filesNote->text);
        self::assertSame(
            'git-activity.json › pull_requests.median_files_changed = absent',
            (string) $filesNote->pointer,
        );

        self::assertStringContainsString('repli sur les lignes', $linesNote->text);
        self::assertStringContainsString('median_lines_changed = 300', $linesNote->text);
        self::assertSame(
            'git-activity.json › pull_requests.median_lines_changed = 300',
            (string) $linesNote->pointer,
        );
    }

    #[Test]
    public function fallsBackToLinesWhenFileCountIsZero(): void
    {
        // Codex review of PR #17: zero is a real value, not an absent field — the fallback
        // note and its pointer must say "= 0", never "absent".
        $profile = $this->profileWith(
            pullRequestsTotal: 71,
            medianFilesChanged: 0.0,
            medianLinesChanged: 45.0,
        );

        $verdict = new SizeEvaluator()->evaluate($profile);

        self::assertSame(Level::Red, $verdict->level);

        $filesNote = $verdict->notes[0];
        self::assertStringContainsString('median_files_changed = 0,', $filesNote->text);
        self::assertStringNotContainsString('absent', $filesNote->text);
        self::assertSame(
            'git-activity.json › pull_requests.median_files_changed = 0',
            (string) $filesNote->pointer,
        );
    }

    #[Test]
    public function bothSignalsAbsentIsNotObservable(): void
    {
        // docs/specs/05-robustesse.md § Signal absent: not a PR-count gap — missingSample is
        // 0 and each absent field carries its own note and pointer.
        $profile = $this->profileWith(
            pullRequestsTotal: 71,
            medianFilesChanged: null,
            medianLinesChanged: null,
        );

        $verdict = new SizeEvaluator()->evaluate($profile);

        self::assertSame(Level::White, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::White, $verdict->confidence->floor);
        self::assertSame(Level::Gold, $verdict->confidence->ceiling);
        self::assertSame(0, $verdict->confidence->missingSample);
        self::assertSame([], $verdict->evidences);

        self::assertCount(2, $verdict->notes);
        [$filesNote, $linesNote] = $verdict->notes;

        self::assertStringContainsString('median_files_changed absent', $filesNote->text);
        self::assertSame(
            'git-activity.json › pull_requests.median_files_changed = absent',
            (string) $filesNote->pointer,
        );

        self::assertStringContainsString('median_lines_changed absent', $linesNote->text);
        self::assertSame(
            'git-activity.json › pull_requests.median_lines_changed = absent',
            (string) $linesNote->pointer,
        );
    }

    #[Test]
    public function tooFewPullRequestsYieldsARangeWithTheCountedOutMissingSample(): void
    {
        // docs/specs/01-axe-taille.md § Tests: total = 3 → range.
        $profile = $this->profileWith(pullRequestsTotal: 3, medianFilesChanged: 13.0);

        $verdict = new SizeEvaluator()->evaluate($profile);

        self::assertSame(Level::Gold, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::Gold, $verdict->confidence->floor);
        self::assertSame(Level::Gold, $verdict->confidence->ceiling);
        self::assertSame(2, $verdict->confidence->missingSample);

        $sampleNote = $verdict->notes[count($verdict->notes) - 1];
        self::assertStringContainsString('pull_requests.total', $sampleNote->text);
        self::assertStringContainsString(' › ', (string) $sampleNote->pointer);
    }

    private function profileWith(
        ?int $pullRequestsTotal,
        ?float $medianFilesChanged,
        ?float $medianLinesChanged = null,
    ): Profile {
        return new Profile(
            identity: new ProfileIdentity(
                id: 'fixture',
                role: 'developer',
                stack: [],
                available: [],
            ),
            gitActivity: new GitActivity(
                period: null,
                pullRequestsTotal: $pullRequestsTotal,
                medianFilesChanged: $medianFilesChanged,
                medianLinesChanged: $medianLinesChanged,
                medianCorrectionCommitsAfterOpen: null,
                mergedWithoutHumanEditAfterOpen: null,
                aiCoauthoredRatio: null,
                maxConcurrentBranches: null,
                medianConcurrentBranches: null,
                contextFiles: null,
            ),
        );
    }
}
