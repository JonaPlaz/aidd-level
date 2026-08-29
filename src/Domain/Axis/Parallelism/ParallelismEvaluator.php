<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Parallelism;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Support\GitActivityPointer;
use AiddLevel\Domain\Axis\Support\SampleCheck;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
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
            // SampleFloors::PARALLELISM_MIN_PR. User-facing text in French, pointer identifier
            // unchanged (docs/specs/00-vue-ensemble.md § 4, commit 8106bbf).
            return new AxisVerdict(
                axis: Axis::Parallelism,
                level: Level::White,
                confidence: new Range(Level::White, Level::Gold, 0),
                evidences: [],
                notes: [
                    new Note(
                        text: 'médiane absente : fournir parallelism.median_concurrent_branches',
                        pointer: GitActivityPointer::of('parallelism.median_concurrent_branches', 'absent'),
                    ),
                ],
            );
        }

        $level = ParallelismThresholds::levelForMedian($median);

        $evidences = [
            new Evidence(
                claim: self::claimForMedian($level, $median),
                pointer: GitActivityPointer::of('parallelism.median_concurrent_branches', self::formatNumber($median)),
            ),
        ];

        $notes = self::peakNote($gitActivity, $median);

        $total = $gitActivity->pullRequestsTotal ?? 0;
        $confidence = SampleCheck::confidence($total, SampleFloors::PARALLELISM_MIN_PR, $level, Level::Gold);

        if ($confidence instanceof Range) {
            $notes[] = new Note(
                text: sprintf(
                    'échantillon insuffisant, plancher %d : %d PR manquantes',
                    SampleFloors::PARALLELISM_MIN_PR,
                    $confidence->missingSample,
                ),
                pointer: GitActivityPointer::of('pull_requests.total', self::formatNumber((float) $total)),
            );
        }

        return new AxisVerdict(
            axis: Axis::Parallelism,
            level: $level,
            confidence: $confidence,
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

        $maxAsString = self::formatNumber((float) $max);

        return [
            new Note(
                text: sprintf('pic observé : max %s, non retenu', $maxAsString),
                pointer: GitActivityPointer::of('parallelism.max_concurrent_branches', $maxAsString),
            ),
        ];
    }

    /**
     * The Green claim reflects the actual median (docs/specs/00-vue-ensemble.md § 4 —
     * a claim must stay verifiable against its pointer), not just the level: exactly 1 reads
     * as "one lane at a time", anything strictly between names the observed median so the
     * claim never contradicts the pointer value it sits next to.
     */
    private static function claimForMedian(Level $level, float $median): string
    {
        return match ($level) {
            Level::White => 'aucun chantier concurrent',
            Level::Green => self::greenClaim($median),
            Level::Gold => 'au moins trois chantiers de front, habituellement',
            default => 'médiane de chantiers concurrents observée',
        };
    }

    private static function greenClaim(float $median): string
    {
        if (1.0 === $median) {
            return 'un chantier à la fois';
        }

        return sprintf(
            '%s chantiers de front en médiane, sous le seuil de 3 de Copper',
            self::formatNumber($median),
        );
    }

    /**
     * A whole median (e.g. 3.0) renders as "3", a fractional one (e.g. 0.5) keeps its decimal.
     */
    private static function formatNumber(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : (string) $value;
    }
}
