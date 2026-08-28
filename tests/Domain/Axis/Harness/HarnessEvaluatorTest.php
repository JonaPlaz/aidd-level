<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Axis\Harness;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Harness\HarnessEvaluator;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Profile\ContextFiles;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\ProfileIdentity;
use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * docs/specs/02-axe-harness.md § Tests: the four calibration profiles, plus the maison
 * fixtures for the cases no calibration profile covers (loop, repo-context absent, white,
 * counters without a memory file).
 */
final class HarnessEvaluatorTest extends TestCase
{
    private HarnessEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new HarnessEvaluator();
    }

    #[Test]
    public function axisIsHarness(): void
    {
        self::assertSame(Axis::Harness, $this->evaluator->axis());
    }

    #[Test]
    public function percevalHasNoMemoryFileAndAiCoauthoredCommitsSoTheAxisIsPromptsRed(): void
    {
        // profiles/perceval/git-activity.json: agents_md=false, counters 0/0/0/0, ratio=0.04.
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: false,
            rules: 0,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: 0.04,
        ));

        self::assertSame(Level::Red, $verdict->level);
        self::assertInstanceOf(Confirmed::class, $verdict->confidence);
        self::assertNotEmpty($verdict->evidences);
        self::assertSame([], $verdict->notes);
    }

    #[Test]
    public function bohortHasAMemoryFileAndNoCountersSoTheAxisIsContextEngineeringBlue(): void
    {
        // profiles/bohort/git-activity.json: agents_md=true, counters 0/0/0/0.
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 0,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: 0.58,
        ));

        self::assertSame(Level::Blue, $verdict->level);
        self::assertSame([], $verdict->notes);
    }

    #[Test]
    public function leodaganHasAMemoryFileAndCountersButNoMatchingRepoContextFileSoTheAxisIsBehaviorCopperWithAnIncoherenceNote(): void
    {
        // profiles/leodagan/git-activity.json: agents_md=true, rules:3 skills:3 hooks:1 agents:2.
        // Its real repo-context/ only has aidd_docs/memory and aidd_docs/tasks: no path
        // matches hooks/agents/rules/skills/settings.json.
        $repoContext = new RepoContext(files: [
            new RepoFile('aidd_docs/memory/architecture.md', '# Architecture'),
            new RepoFile('aidd_docs/memory/coding-assertions.md', '# Assertions'),
            new RepoFile('aidd_docs/tasks/1102.md', '# Task 1102'),
        ]);

        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 3,
            skills: 3,
            hooks: 1,
            agents: 2,
            ratio: 0.87,
            repoContext: $repoContext,
        ));

        self::assertSame(Level::Copper, $verdict->level);
        self::assertCount(1, $verdict->notes);
        self::assertStringContainsString('no repo-context file path matches', $verdict->notes[0]->text);
    }

    #[Test]
    public function arthurHasAMemoryFileAndCountersSoTheAxisIsBehaviorCopper(): void
    {
        // profiles/arthur/git-activity.json: agents_md=true, rules:0 skills:4 hooks:0 agents:2.
        $repoContext = new RepoContext(files: [
            new RepoFile('AGENTS.md', '# Agents'),
            new RepoFile('docs/context/vcs.md', '# VCS'),
            new RepoFile(
                'docs/brainstorm/2026-06-auto-retry.md',
                'Loop: run the task, run its check, feed the failure back, run again. '
                .'Stop after N attempts or when the check passes. Not decided.',
            ),
        ]);

        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 0,
            skills: 4,
            hooks: 0,
            agents: 2,
            ratio: 0.91,
            repoContext: $repoContext,
        ));

        self::assertSame(Level::Copper, $verdict->level, 'a docs/brainstorm/ file must never be read as a loop');
    }

    #[Test]
    public function aRepoContextFileUnderHooksMaterializesTheCountedBehaviorAsEvidence(): void
    {
        $repoContext = new RepoContext(files: [
            new RepoFile('.claude/hooks/check-assertions.js', 'module.exports = () => {};'),
            new RepoFile('.claude/settings.json', '{}'),
        ]);

        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 3,
            skills: 3,
            hooks: 1,
            agents: 2,
            ratio: 0.87,
            repoContext: $repoContext,
        ));

        self::assertSame(Level::Copper, $verdict->level);
        self::assertSame([], $verdict->notes);
        $pointers = array_map(static fn ($evidence) => (string) $evidence->pointer, $verdict->evidences);
        self::assertTrue(
            (bool) array_filter($pointers, static fn (string $pointer): bool => str_contains($pointer, '.claude/hooks/check-assertions.js')),
            'expected a cited repo-context file materializing behavior',
        );
    }

    #[Test]
    public function aMakefileWithABoundedRetryPromotesBehaviorToLoopsGold(): void
    {
        $repoContext = new RepoContext(files: [
            new RepoFile('.claude/hooks/check-assertions.js', 'module.exports = () => {};'),
            new RepoFile('Makefile', "check:\n\tfor i in 1 2 3; do ./run.sh && exit 0; done; echo 'max_attempts=3 reached'"),
        ]);

        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 3,
            skills: 3,
            hooks: 1,
            agents: 2,
            ratio: 0.87,
            repoContext: $repoContext,
        ));

        self::assertSame(Level::Gold, $verdict->level);
        $pointers = array_map(static fn ($evidence) => (string) $evidence->pointer, $verdict->evidences);
        self::assertTrue(
            (bool) array_filter($pointers, static fn (string $pointer): bool => str_contains($pointer, 'Makefile')),
            'expected the Makefile to be cited as loop evidence',
        );
    }

    #[Test]
    public function aRetryPatternUnderDocsIsNeverReadAsALoop(): void
    {
        $repoContext = new RepoContext(files: [
            new RepoFile('.claude/hooks/check-assertions.js', 'module.exports = () => {};'),
            new RepoFile(
                'docs/brainstorm/2026-06-auto-retry.md',
                'retry until it passes, max_attempts=3',
            ),
        ]);

        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 3,
            skills: 3,
            hooks: 1,
            agents: 2,
            ratio: 0.87,
            repoContext: $repoContext,
        ));

        self::assertSame(Level::Copper, $verdict->level, 'docs/ must never contribute to loop detection');
    }

    #[Test]
    public function arthurWithoutRepoContextCapsAtCopperWithALoopsNotObservableNote(): void
    {
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 0,
            skills: 4,
            hooks: 0,
            agents: 2,
            ratio: 0.91,
            repoContext: null,
        ));

        self::assertSame(Level::Copper, $verdict->level);
        self::assertCount(1, $verdict->notes);
        self::assertSame(
            'loops not observable, repo-context/ absent',
            $verdict->notes[0]->text,
        );
    }

    #[Test]
    public function aProfileWithNoRatioAndNoCountersIsWhite(): void
    {
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: false,
            rules: 0,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: 0.0,
        ));

        self::assertSame(Level::White, $verdict->level);
        self::assertSame([], $verdict->notes);
    }

    #[Test]
    public function countersWithoutAMemoryFileAreCappedAtPromptsWithANote(): void
    {
        // Constated case, unproven on real data (§ Règle): counters > 0, agents_md = false.
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: false,
            rules: 2,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: 0.0,
        ));

        self::assertSame(Level::Red, $verdict->level);
        self::assertCount(1, $verdict->notes);
        self::assertSame(
            'counters without memory file; the grid cumulates',
            $verdict->notes[0]->text,
        );
        self::assertInstanceOf(Note::class, $verdict->notes[0]);
    }

    private function profile(
        bool $agentsMd,
        int $rules,
        int $skills,
        int $hooks,
        int $agents,
        float $ratio,
        ?RepoContext $repoContext = null,
    ): Profile {
        $contextFiles = new ContextFiles(
            agentsMd: $agentsMd,
            rules: $rules,
            skills: $skills,
            hooks: $hooks,
            agents: $agents,
        );

        $gitActivity = new GitActivity(
            period: null,
            pullRequestsTotal: null,
            medianFilesChanged: null,
            medianLinesChanged: null,
            medianCorrectionCommitsAfterOpen: null,
            mergedWithoutHumanEditAfterOpen: null,
            aiCoauthoredRatio: $ratio,
            maxConcurrentBranches: null,
            medianConcurrentBranches: null,
            contextFiles: $contextFiles,
        );

        return new Profile(
            identity: new ProfileIdentity(
                id: 'fixture',
                role: 'developer',
                stack: [],
                available: [],
            ),
            gitActivity: $gitActivity,
            repoContext: $repoContext,
        );
    }
}
