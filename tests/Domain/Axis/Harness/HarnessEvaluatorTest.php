<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Axis\Harness;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Harness\HarnessEvaluator;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
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
    public function leodaganRealRepoContextCitesAnActualBehaviorFileWithNoIncoherenceNote(): void
    {
        // profiles/leodagan/git-activity.json: agents_md=true, rules:3 skills:3 hooks:1 agents:2.
        // Its real repo-context/ has .claude/{agents,hooks,rules,skills,settings.json}: the
        // counters are matched by real files, so the proof is cited, not incoherent.
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 3,
            skills: 3,
            hooks: 1,
            agents: 2,
            ratio: 0.87,
            repoContext: $this->realRepoContext('leodagan'),
        ));

        self::assertSame(Level::Copper, $verdict->level);
        // A real behavior file was found: the only note left is « no loop found », never
        // the incoherence note (the counters do have a matching repo-context file here).
        self::assertCount(1, $verdict->notes);
        self::assertSame('boucles : aucune relance bornée trouvée', $verdict->notes[0]->text);
        self::assertTrue(
            $this->evidencesCite($verdict->evidences, '.claude/'),
            'expected a real .claude/ file to be cited as behavior proof',
        );
    }

    #[Test]
    public function arthurRealRepoContextCitesAnActualBehaviorFileAndNeverReadsItsBrainstormAsALoop(): void
    {
        // profiles/arthur/git-activity.json: agents_md=true, rules:0 skills:4 hooks:0 agents:2.
        // Its real repo-context/ has .claude/{agents,skills,settings.json} and, separately,
        // docs/brainstorm/2026-06-auto-retry.md — explicitly "Not decided", never a loop.
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 0,
            skills: 4,
            hooks: 0,
            agents: 2,
            ratio: 0.91,
            repoContext: $this->realRepoContext('arthur'),
        ));

        self::assertSame(Level::Copper, $verdict->level, 'a docs/brainstorm/ file must never be read as a loop');
        self::assertCount(1, $verdict->notes);
        self::assertSame('boucles : aucune relance bornée trouvée', $verdict->notes[0]->text);
        self::assertTrue(
            $this->evidencesCite($verdict->evidences, '.claude/'),
            'expected a real .claude/ file to be cited as behavior proof',
        );
    }

    #[Test]
    public function countersPresentWithNoMatchingRepoContextFileGetAnIncoherenceNote(): void
    {
        // Synthetic: a repo-context/ present but with no path under hooks/agents/rules/
        // skills/settings.json, unlike any of the four calibration profiles.
        $repoContext = new RepoContext(files: [
            new RepoFile('aidd_docs/memory/architecture.md', '# Architecture'),
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
        self::assertCount(2, $verdict->notes);
        self::assertStringContainsString(
            'compteurs présents sans fichier repo-context correspondant',
            $verdict->notes[0]->text,
        );
        self::assertSame('boucles : aucune relance bornée trouvée', $verdict->notes[1]->text);
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
        self::assertCount(1, $verdict->notes);
        self::assertSame('boucles : aucune relance bornée trouvée', $verdict->notes[0]->text);
        self::assertTrue(
            $this->evidencesCite($verdict->evidences, '.claude/hooks/check-assertions.js'),
            'expected a cited repo-context file materializing behavior',
        );
    }

    #[Test]
    public function aMakefileWithABoundedRetryPromotesBehaviorToLoopsGold(): void
    {
        $repoContext = new RepoContext(files: [
            new RepoFile('.claude/hooks/check-assertions.js', 'module.exports = () => {};'),
            new RepoFile('Makefile', "check:\n\tuntil ./run.sh; do echo retrying; done\n\t# max_attempts=3"),
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
        self::assertTrue(
            $this->evidencesCite($verdict->evidences, 'Makefile'),
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
        self::assertCount(1, $verdict->notes);
        self::assertSame('boucles : aucune relance bornée trouvée', $verdict->notes[0]->text);
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
            'boucles non observables : repo-context/ absent',
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
    public function aMissingRatioWithNoMemoryFileAndZeroCountersIsARangeWithANote(): void
    {
        // docs/specs/02-axe-harness.md § Ratio absent: agents_md=false, counters known at 0,
        // ratio null — the axis cannot decide between "prompts" (Red) and "rien" (White).
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: false,
            rules: 0,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: null,
        ));

        self::assertSame(Level::White, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::White, $verdict->confidence->floor);
        self::assertSame(Level::Red, $verdict->confidence->ceiling);
        self::assertSame(0, $verdict->confidence->missingSample);
        self::assertSame([], $verdict->evidences);
        self::assertCount(1, $verdict->notes);
        self::assertSame(
            'ratio absent : impossible de départager prompts de rien',
            $verdict->notes[0]->text,
        );
        self::assertSame('commits.ai_coauthored_ratio', $verdict->notes[0]->pointer->field);
        self::assertSame('absent', $verdict->notes[0]->pointer->value);
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
            'des règles/agents sont comptés sans fichier mémoire ; la grille cumule, le '
            .'niveau ne peut pas sauter context engineering',
            $verdict->notes[0]->text,
        );
        self::assertInstanceOf(Note::class, $verdict->notes[0]);
    }

    #[Test]
    public function aNullAgentsMdMakesTheAxisNonObservable(): void
    {
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: null,
            rules: 0,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: 0.0,
        ));

        self::assertSame(Level::White, $verdict->level);
        self::assertInstanceOf(Range::class, $verdict->confidence);
        self::assertSame(Level::White, $verdict->confidence->floor);
        self::assertSame(Level::Gold, $verdict->confidence->ceiling);
        self::assertSame(0, $verdict->confidence->missingSample);
        self::assertCount(1, $verdict->notes);
        self::assertStringContainsString('agents_md', $verdict->notes[0]->text);
        self::assertStringContainsString('absent', $verdict->notes[0]->text);
    }

    #[Test]
    public function aNullCounterWithNoKnownPositiveCounterCapsAtContextEngineeringWithANote(): void
    {
        // agents_md is known true, but the rules counter is absent from the file and the
        // three known counters are all zero: Behavior cannot be confirmed nor ruled out.
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: null,
            skills: 0,
            hooks: 0,
            agents: 0,
            ratio: 0.0,
        ));

        self::assertSame(Level::Blue, $verdict->level);
        self::assertCount(1, $verdict->notes);
        self::assertStringContainsString('behavior non observable', $verdict->notes[0]->text);
        self::assertSame('context_files.rules_count', $verdict->notes[0]->pointer->field);
        self::assertSame('absent', $verdict->notes[0]->pointer->value);
    }

    #[Test]
    public function aNullCounterIsNeverRenderedAsZeroWhenBehaviorIsAlreadyConfirmed(): void
    {
        // agents_md true, hooks known positive (confirms Behavior on its own); skills is
        // absent from the file and must never be presented as "0 skills".
        $verdict = $this->evaluator->evaluate($this->profile(
            agentsMd: true,
            rules: 0,
            skills: null,
            hooks: 1,
            agents: 0,
            ratio: 0.0,
            repoContext: new RepoContext(files: [
                new RepoFile('.claude/hooks/check-assertions.js', 'module.exports = () => {};'),
            ]),
        ));

        self::assertSame(Level::Copper, $verdict->level);
        $claims = array_map(static fn (Evidence $evidence): string => $evidence->claim, $verdict->evidences);
        self::assertTrue(
            (bool) array_filter($claims, static fn (string $claim): bool => str_contains($claim, 'skill absent')),
            'a null skills counter must read "absent", never "0 skills"',
        );
        // agents_md and the counters are each cited by their own Evidence (Codex review of #19).
        self::assertTrue(
            (bool) array_filter($claims, static fn (string $claim): bool => str_contains($claim, 'context engineering')),
        );
        self::assertTrue(
            (bool) array_filter($claims, static fn (string $claim): bool => str_contains($claim, 'behavior')),
        );
    }

    #[Test]
    public function aBoundDeclarationAloneIsNeverReadAsARetryConstruct(): void
    {
        // "MAX_ATTEMPTS = 3" alone declares a bound but restarts nothing: RETRY and BOUND
        // must not both be satisfied by the same "attempt" token (Codex review of #19).
        $repoContext = new RepoContext(files: [
            new RepoFile('.claude/hooks/check-assertions.js', 'module.exports = () => {};'),
            new RepoFile('scripts/config.js', 'export const MAX_ATTEMPTS = 3;'),
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

        self::assertSame(Level::Copper, $verdict->level, 'a bound declaration alone is not a retry loop');
    }

    /**
     * Loads `profiles/<profile>/repo-context/` from disk, hidden files included
     * (`.claude/…`) — the real tree, not a hand-picked subset of it.
     */
    private function realRepoContext(string $profile): RepoContext
    {
        $root = dirname(__DIR__, 4).'/profiles/'.$profile.'/repo-context';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $pathname = $fileInfo->getPathname();
            $relativePath = substr($pathname, \strlen($root) + 1);
            $content = file_get_contents($pathname);
            $files[] = new RepoFile($relativePath, false === $content ? '' : $content);
        }

        return new RepoContext($files);
    }

    /**
     * @param list<Evidence> $evidences
     */
    private function evidencesCite(array $evidences, string $needle): bool
    {
        foreach ($evidences as $evidence) {
            if (str_contains((string) $evidence->pointer, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function profile(
        ?bool $agentsMd,
        ?int $rules,
        ?int $skills,
        ?int $hooks,
        ?int $agents,
        ?float $ratio,
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
