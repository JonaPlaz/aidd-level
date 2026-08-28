<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;

/**
 * The Harness axis (docs/specs/02-axe-harness.md): what the person built around the model —
 * context engineering (memory), behavior (rules/agents/hooks) and bounded loops. The grid
 * cumulates: behavior does not exist without context engineering, loops do not exist without
 * behavior. Every field read from `git-activity.json` is nullable (docs/specs/05-robustesse.md
 * § Gate); this evaluator never fails on a missing field, it treats it as absent.
 */
final class HarnessEvaluator implements AxisEvaluator
{
    private const string SOURCE_FILE = 'git-activity.json';

    private const string COUNTERS_WITHOUT_MEMORY_NOTE =
        'counters without memory file; the grid cumulates';

    private const string LOOPS_NOT_OBSERVABLE_NOTE =
        'loops not observable, repo-context/ absent';

    /**
     * A hook or an agent is always Behavior, never Context engineering (§ Signal,
     * « rattachement »): a repo-context path matching one of these is structural proof that
     * a counted rule/skill/hook/agent actually exists on disk.
     *
     * @var list<string>
     */
    private const array BEHAVIOR_PROOF_MARKERS = ['hooks', 'agents', 'rules', 'skills', 'settings.json'];

    public function axis(): Axis
    {
        return Axis::Harness;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $contextFiles = $profile->gitActivity->contextFiles;

        $agentsMd = false;
        $rules = 0;
        $skills = 0;
        $hooks = 0;
        $agents = 0;

        if (null !== $contextFiles) {
            $agentsMd = $contextFiles->agentsMd ?? false;
            $rules = $contextFiles->rules ?? 0;
            $skills = $contextFiles->skills ?? 0;
            $hooks = $contextFiles->hooks ?? 0;
            $agents = $contextFiles->agents ?? 0;
        }
        $countersSum = $rules + $skills + $hooks + $agents;
        $ratio = $profile->gitActivity->aiCoauthoredRatio ?? 0.0;

        $evidences = [];
        $notes = [];

        if ($agentsMd && $countersSum > 0) {
            $level = HarnessLevel::Behavior;
            $evidences[] = new Evidence(
                'A memory file plus counted rules/skills/hooks/agents place the axis at behavior.',
                $this->countersPointer($rules, $skills, $hooks, $agents),
            );
        } elseif ($agentsMd) {
            $level = HarnessLevel::ContextEngineering;
            $evidences[] = new Evidence(
                'A memory file (agents_md) places the axis at context engineering.',
                new Pointer(self::SOURCE_FILE, 'context_files.agents_md', 'true'),
            );
        } elseif ($countersSum > 0) {
            $level = HarnessLevel::Prompts;
            $evidences[] = new Evidence(
                'Rules/skills/hooks/agents are counted without a memory file.',
                $this->countersPointer($rules, $skills, $hooks, $agents),
            );
            $notes[] = new Note(
                self::COUNTERS_WITHOUT_MEMORY_NOTE,
                new Pointer(self::SOURCE_FILE, 'context_files.agents_md', 'false'),
            );
        } elseif ($ratio > 0.0) {
            $level = HarnessLevel::Prompts;
            $evidences[] = new Evidence(
                'AI-coauthored commits without any context file place the axis at prompts.',
                new Pointer(self::SOURCE_FILE, 'commits.ai_coauthored_ratio', (string) $ratio),
            );
        } else {
            $level = HarnessLevel::None;
            $evidences[] = new Evidence(
                'No AI-coauthored commits and no context file counters.',
                new Pointer(self::SOURCE_FILE, 'commits.ai_coauthored_ratio', (string) $ratio),
            );
        }

        if (HarnessLevel::Behavior === $level) {
            [$level, $behaviorEvidences, $behaviorNotes] = $this->resolveBehaviorCeiling(
                $profile->repoContext,
                $rules,
                $skills,
                $hooks,
                $agents,
            );
            array_push($evidences, ...$behaviorEvidences);
            array_push($notes, ...$behaviorNotes);
        }

        return new AxisVerdict(
            axis: Axis::Harness,
            level: $level->toLevel(),
            confidence: new Confirmed(),
            evidences: $evidences,
            notes: $notes,
        );
    }

    /**
     * @return array{0: HarnessLevel, 1: list<Evidence>, 2: list<Note>}
     */
    private function resolveBehaviorCeiling(
        ?RepoContext $repoContext,
        int $rules,
        int $skills,
        int $hooks,
        int $agents,
    ): array {
        $evidences = [];
        $notes = [];

        if (null === $repoContext) {
            $notes[] = new Note(
                self::LOOPS_NOT_OBSERVABLE_NOTE,
                $this->countersPointer($rules, $skills, $hooks, $agents),
            );

            return [HarnessLevel::Behavior, $evidences, $notes];
        }

        $proofFile = $this->findBehaviorProof($repoContext);
        if (null !== $proofFile) {
            $evidences[] = new Evidence(
                'A repo-context file materializes the counted behavior.',
                new Pointer(sprintf('repo-context/%s', $proofFile->path), 'file', 'present'),
            );
        } else {
            $notes[] = new Note(
                'behavior counters are present but no repo-context file path matches '
                .'hooks/agents/rules/skills/settings.json; the counter is authoritative.',
                $this->countersPointer($rules, $skills, $hooks, $agents),
            );
        }

        $loopFile = LoopDetector::detect($repoContext);
        if (null === $loopFile) {
            return [HarnessLevel::Behavior, $evidences, $notes];
        }

        $evidences[] = new Evidence(
            'A bounded retry loop was found in repo-context/.',
            new Pointer(sprintf('repo-context/%s', $loopFile->path), 'loop', 'retry and bound pattern matched'),
        );

        return [HarnessLevel::Loops, $evidences, $notes];
    }

    private function findBehaviorProof(RepoContext $repoContext): ?RepoFile
    {
        foreach ($repoContext->files as $file) {
            foreach (self::BEHAVIOR_PROOF_MARKERS as $marker) {
                if (str_contains($file->path, $marker)) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function countersPointer(int $rules, int $skills, int $hooks, int $agents): Pointer
    {
        return new Pointer(
            self::SOURCE_FILE,
            'context_files',
            sprintf('{rules:%d, skills:%d, hooks:%d, agents:%d}', $rules, $skills, $hooks, $agents),
        );
    }
}
