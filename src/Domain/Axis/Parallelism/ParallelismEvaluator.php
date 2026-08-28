<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Parallelism;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Threshold\ParallelismThresholds;
use AiddLevel\Domain\Threshold\SampleFloors;

/**
 * The Parallelism axis (docs/specs/04-axe-parallele.md): "how many chantiers move at once,
 * habitually — a lone peak does not count". `median_concurrent_branches` decides; the
 * maximum never does, it only feeds a "peak observed, not retained" note.
 */
final readonly class ParallelismEvaluator implements AxisEvaluator
{
    private const string ACTIVITY_FILE = 'git-activity.json';

    public function axis(): Axis
    {
        return Axis::Parallelism;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $gitActivity = $profile->gitActivity;
        $median = $gitActivity->medianConcurrentBranches;

        if (null === $median) {
            // Absent signal, not a short sample: docs/specs/05-robustesse.md § Signal absent —
            // the gap is the field itself, not a PR count, so missingSample is 0, not
            // SampleFloors::PARALLELISM_MIN_PR.
            return new AxisVerdict(
                axis: Axis::Parallelism,
                level: Level::White,
                confidence: new Range(Level::White, Level::Gold, 0),
                evidences: [],
                notes: [
                    new Note(
                        text: 'not observable — median concurrent branches missing, provide it',
                        pointer: new Pointer(self::ACTIVITY_FILE, 'parallelism.median_concurrent_branches', 'absent'),
                    ),
                ],
            );
        }

        $level = ParallelismThresholds::levelForMedian($median);

        $evidences = [
            new Evidence(
                claim: self::claimForLevel($level),
                pointer: new Pointer(self::ACTIVITY_FILE, 'parallelism.median_concurrent_branches', self::formatNumber($median)),
            ),
        ];

        $notes = self::peakNote($gitActivity, $median);

        $total = $gitActivity->pullRequestsTotal;
        if (null === $total || $total < SampleFloors::PARALLELISM_MIN_PR) {
            $missing = SampleFloors::PARALLELISM_MIN_PR - ($total ?? 0);

            return new AxisVerdict(
                axis: Axis::Parallelism,
                level: $level,
                confidence: new Range($level, Level::Gold, $missing),
                evidences: $evidences,
                notes: $notes,
            );
        }

        return new AxisVerdict(
            axis: Axis::Parallelism,
            level: $level,
            confidence: new Confirmed(),
            evidences: $evidences,
            notes: $notes,
        );
    }

    /**
     * @return list<Note>
     */
    private static function peakNote(GitActivity $gitActivity, float $median): array
    {
        $max = $gitActivity->maxConcurrentBranches;

        if (null === $max || $max <= $median) {
            return [];
        }

        return [
            new Note(
                text: 'peak observed, not retained',
                pointer: new Pointer(self::ACTIVITY_FILE, 'parallelism.max_concurrent_branches', self::formatNumber((float) $max)),
            ),
        ];
    }

    private static function claimForLevel(Level $level): string
    {
        return match ($level) {
            Level::White => 'no concurrent branch at all',
            Level::Green => 'one lane at a time; the "3" cell of Copper is not met',
            Level::Gold => '3 is a minimum, satisfied from Copper to Gold',
            default => 'median concurrent branches observed',
        };
    }

    /**
     * A whole median (e.g. 3.0) renders as "3", a fractional one (e.g. 0.5) keeps its decimal.
     */
    private static function formatNumber(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : (string) $value;
    }
}
