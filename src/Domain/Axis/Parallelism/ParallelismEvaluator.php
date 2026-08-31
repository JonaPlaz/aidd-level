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
     * The claim names the median and the threshold it sits against (docs/specs/06 § 3 —
     * "aucun chiffre nu"), never a bare band name: exactly 1 reads as "one lane at a time",
     * anything strictly between names the observed median so the claim never contradicts the
     * pointer value it sits next to.
     */
    private static function claimForMedian(Level $level, float $median): string
    {
        $value = self::formatNumber($median);

        return match ($level) {
            Level::White => sprintf(
                "d'habitude %s chantier mené en même temps (médiane) : sous le seuil de %d de Green.",
                $value,
                ParallelismThresholds::MEDIAN_SOME_MIN,
            ),
            Level::Green => self::greenClaim($median),
            Level::Gold => sprintf(
                "d'habitude %s chantiers menés en même temps (médiane) : au moins le seuil de %d de Gold.",
                $value,
                ParallelismThresholds::MEDIAN_HABITUAL_MIN,
            ),
            default => sprintf("d'habitude %s chantiers menés en même temps (médiane)", $value),
        };
    }

    private static function greenClaim(float $median): string
    {
        if (1.0 === $median) {
            return sprintf(
                'un chantier à la fois : sous le seuil de %d de Copper.',
                ParallelismThresholds::MEDIAN_HABITUAL_MIN,
            );
        }

        return sprintf(
            "d'habitude %s chantiers menés en même temps (médiane), sous le seuil de %d de Copper.",
            self::formatNumber($median),
            ParallelismThresholds::MEDIAN_HABITUAL_MIN,
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
