<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
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
 * § Gate); a missing field is never coerced into a negative value (`false`/`0`) — a null
 * `agents_md` makes the whole axis non-observable, a null counter alone only caps Behavior,
 * it never fabricates a "no rules" claim that is not in the file.
 *
 * User-facing text — every `Evidence` claim and `Note` text — is French: it is read by the
 * jury (docs/specs/00-vue-ensemble.md § Namespace, docs/specs/06-sortie-et-progression.md).
 * Pointer identifiers (file, field) stay as they are in the source JSON.
 */
final class HarnessEvaluator implements AxisEvaluator
{
    private const string SOURCE_FILE = 'git-activity.json';

    // Verbatim quote from docs/specs/02-axe-harness.md § Règle.
    private const string COUNTERS_WITHOUT_MEMORY_NOTE =
        'des règles/agents sont comptés sans fichier mémoire ; la grille cumule, le niveau '
        .'ne peut pas sauter context engineering';

    private const string NO_LOOP_FOUND_NOTE = 'boucles : aucune relance bornée trouvée';

    /**
     * The four counter fields, mapped to their name in `git-activity.json › context_files`
     * and to the artifact path pattern that materializes them (docs/specs/02-axe-harness.md
     * § Preuve structurelle): a rule/skill/hook/agent is proven by a real path segment, not
     * by any file whose name merely contains the word.
     *
     * @var array<string, string>
     */
    private const array COUNTER_FIELD_NAMES = [
        'rules' => 'context_files.rules_count',
        'skills' => 'context_files.skills_count',
        'hooks' => 'context_files.hooks_count',
        'agents' => 'context_files.agents_count',
    ];

    /**
     * @var array<string, string>
     */
    private const array CATEGORY_PROOF_PATTERNS = [
        'hooks' => '#(?:^|/)hooks/[^/]+\.(?:js|sh)$#i',
        'agents' => '#(?:^|/)agents/[^/]+\.md$#i',
        'rules' => '#(?:^|/)rules/[^/]+\.md$#i',
        'skills' => '#(?:^|/)skills/[^/]+/SKILL\.md$#i',
    ];

    public function axis(): Axis
    {
        return Axis::Harness;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $contextFiles = $profile->gitActivity->contextFiles;

        if (null === $contextFiles || null === $contextFiles->agentsMd) {
            return new AxisVerdict(
                axis: Axis::Harness,
                level: Level::White,
                confidence: new Range(Level::White, Level::Gold, missingSample: 0),
                evidences: [],
                notes: [new Note(
                    'agents_md : donnée absente, axe non observable',
                    new Pointer(self::SOURCE_FILE, 'context_files.agents_md', 'absent'),
                )],
            );
        }

        $agentsMd = $contextFiles->agentsMd;
        $ratio = $profile->gitActivity->aiCoauthoredRatio ?? 0.0;
        $counters = [
            'rules' => $contextFiles->rules,
            'skills' => $contextFiles->skills,
            'hooks' => $contextFiles->hooks,
            'agents' => $contextFiles->agents,
        ];
        $knownSum = array_sum(array_filter($counters, static fn (?int $c): bool => null !== $c));
        $firstNullCounter = array_search(null, $counters, true);

        $evidences = [];
        $notes = [];

        if ($agentsMd && $knownSum > 0) {
            $level = HarnessLevel::Behavior;
            $evidences[] = $this->agentsMdEvidence();
            $evidences[] = new Evidence(
                sprintf('behavior : %s', $this->countersLabel($counters)),
                $this->countersPointer($counters),
            );
        } elseif ($agentsMd && false !== $firstNullCounter) {
            // A counter is unknown and the known ones sum to zero: Behavior is not
            // observable, but context engineering is confirmed (§ Signal absent).
            $level = HarnessLevel::ContextEngineering;
            $evidences[] = $this->agentsMdEvidence();
            $notes[] = new Note(
                'behavior non observable : au moins un compteur de contexte absent',
                new Pointer(self::SOURCE_FILE, self::COUNTER_FIELD_NAMES[$firstNullCounter], 'absent'),
            );
        } elseif ($agentsMd) {
            $level = HarnessLevel::ContextEngineering;
            $evidences[] = $this->agentsMdEvidence();
        } elseif ($knownSum > 0) {
            $level = HarnessLevel::Prompts;
            $evidences[] = new Evidence(
                sprintf('prompts : %s sans fichier mémoire', $this->countersLabel($counters)),
                $this->countersPointer($counters),
            );
            $notes[] = new Note(
                self::COUNTERS_WITHOUT_MEMORY_NOTE,
                new Pointer(self::SOURCE_FILE, 'context_files.agents_md', 'false'),
            );
        } elseif ($ratio > 0.0) {
            $level = HarnessLevel::Prompts;
            $evidences[] = new Evidence(
                "prompts : commits co-écrits avec l'IA, aucun fichier de contexte",
                new Pointer(self::SOURCE_FILE, 'commits.ai_coauthored_ratio', (string) $ratio),
            );
        } else {
            $level = HarnessLevel::None;
            $evidences[] = new Evidence(
                "rien : aucun commit co-écrit avec l'IA, aucun compteur de contexte",
                new Pointer(self::SOURCE_FILE, 'commits.ai_coauthored_ratio', (string) $ratio),
            );
        }

        if (HarnessLevel::Behavior === $level) {
            [$level, $behaviorEvidences, $behaviorNotes] = $this->resolveBehaviorCeiling(
                $profile->repoContext,
                $counters,
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
     * @param array{rules: int|null, skills: int|null, hooks: int|null, agents: int|null} $counters
     *
     * @return array{0: HarnessLevel, 1: list<Evidence>, 2: list<Note>}
     */
    private function resolveBehaviorCeiling(?RepoContext $repoContext, array $counters): array
    {
        $evidences = [];
        $notes = [];

        if (null === $repoContext) {
            $notes[] = new Note(
                'boucles non observables : repo-context/ absent',
                new Pointer('repo-context/', 'directory', 'absent'),
            );

            return [HarnessLevel::Behavior, $evidences, $notes];
        }

        $proofFile = $this->findBehaviorProof($repoContext, $counters);
        if (null !== $proofFile) {
            $evidences[] = new Evidence(
                'behavior : preuve structurelle trouvée dans repo-context/',
                new Pointer(sprintf('repo-context/%s', $proofFile->path), 'file', 'present'),
            );
        } else {
            $notes[] = new Note(
                'behavior : compteurs présents sans fichier repo-context correspondant aux '
                .'catégories comptées ; le compteur fait foi',
                $this->countersPointer($counters),
            );
        }

        $loopFile = LoopDetector::detect($repoContext);
        if (null === $loopFile) {
            $notes[] = new Note(
                self::NO_LOOP_FOUND_NOTE,
                new Pointer('repo-context/', 'bounded retry', 'none found'),
            );

            return [HarnessLevel::Behavior, $evidences, $notes];
        }

        $evidences[] = new Evidence(
            'boucles : relance bornée détectée dans repo-context/',
            new Pointer(sprintf('repo-context/%s', $loopFile->path), 'loop', 'retry and bound pattern matched'),
        );

        return [HarnessLevel::Loops, $evidences, $notes];
    }

    /**
     * Only the categories with a positive counter are searched, on real artifact path
     * segments (docs/specs/02-axe-harness.md § Preuve structurelle) — a rule is a
     * `rules/*.md` file, not any path that merely contains the word "rules".
     *
     * @param array{rules: int|null, skills: int|null, hooks: int|null, agents: int|null} $counters
     */
    private function findBehaviorProof(RepoContext $repoContext, array $counters): ?RepoFile
    {
        $activeCategories = array_keys(array_filter($counters, static fn (?int $c): bool => null !== $c && $c > 0));

        foreach ($repoContext->files as $file) {
            foreach ($activeCategories as $category) {
                if (1 === preg_match(self::CATEGORY_PROOF_PATTERNS[$category], $file->path)) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function agentsMdEvidence(): Evidence
    {
        return new Evidence(
            'context engineering : fichier mémoire présent',
            new Pointer(self::SOURCE_FILE, 'context_files.agents_md', 'true'),
        );
    }

    /**
     * @param array{rules: int|null, skills: int|null, hooks: int|null, agents: int|null} $counters
     */
    private function countersPointer(array $counters): Pointer
    {
        return new Pointer(
            self::SOURCE_FILE,
            'context_files',
            sprintf(
                '{rules:%s, skills:%s, hooks:%s, agents:%s}',
                $this->counterValue($counters['rules']),
                $this->counterValue($counters['skills']),
                $this->counterValue($counters['hooks']),
                $this->counterValue($counters['agents']),
            ),
        );
    }

    /**
     * A French, human-readable rendering of the four counters, e.g. « 3 règles, 3 skills,
     * 1 hook, 2 agents » (docs/specs/02-axe-harness.md § Preuves rendues). A null counter
     * is rendered as « absent », never as zero.
     *
     * @param array{rules: int|null, skills: int|null, hooks: int|null, agents: int|null} $counters
     */
    private function countersLabel(array $counters): string
    {
        return sprintf(
            '%s, %s, %s, %s',
            $this->counterFragment($counters['rules'], 'règle'),
            $this->counterFragment($counters['skills'], 'skill'),
            $this->counterFragment($counters['hooks'], 'hook'),
            $this->counterFragment($counters['agents'], 'agent'),
        );
    }

    private function counterFragment(?int $count, string $singular): string
    {
        if (null === $count) {
            return sprintf('%s absent', $singular);
        }

        return $this->pluralize($count, $singular);
    }

    private function counterValue(?int $count): string
    {
        return null === $count ? 'absent' : (string) $count;
    }

    private function pluralize(int $count, string $singular): string
    {
        return sprintf('%d %s', $count, 1 === $count ? $singular : $singular.'s');
    }
}
