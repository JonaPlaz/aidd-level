<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Size;

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
use AiddLevel\Domain\Threshold\SampleFloors;
use AiddLevel\Domain\Threshold\SizeBand;
use AiddLevel\Domain\Threshold\SizeThresholds;

/**
 * The Size axis (docs/specs/01-axe-taille.md): the habitual size of AI-delivered features.
 * `median_files_changed` decides first — S and L/XL are defined structurally ("multi-étapes",
 * "multi-modules"), so file count fits the definition better than line volume. Falls back to
 * `median_lines_changed` only when the file count is absent or zero. Never a maximum, never
 * `size_distribution` (docs/specs/01-axe-taille.md § Signal).
 */
final readonly class SizeEvaluator implements AxisEvaluator
{
    /**
     * Neither signal is present: the axis cannot be observed at all, not merely under-sampled.
     * The whole floor-to-Gold range is counted as missing (docs/specs/01-axe-taille.md, dev
     * clarification: two absent signals are a distinct case from an insufficient PR sample).
     */
    private const int MISSING_SAMPLE_WHEN_NOT_OBSERVABLE = SampleFloors::MIN_PR_SAMPLE;

    public function axis(): Axis
    {
        return Axis::Size;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $gitActivity = $profile->gitActivity;
        $files = $gitActivity->medianFilesChanged;
        $lines = $gitActivity->medianLinesChanged;

        if (null !== $files && 0.0 !== $files) {
            $band = SizeThresholds::bandForFiles($files);
            $signalEvidence = new Evidence(
                claim: self::claimFor($band),
                pointer: new Pointer(
                    file: 'git-activity.json',
                    field: 'pull_requests.median_files_changed',
                    value: (string) $files,
                ),
            );
            $notes = [];
        } elseif (null !== $lines) {
            $band = SizeThresholds::bandForLines($lines);
            $signalEvidence = new Evidence(
                claim: self::claimFor($band),
                pointer: new Pointer(
                    file: 'git-activity.json',
                    field: 'pull_requests.median_lines_changed',
                    value: (string) $lines,
                ),
            );
            $notes = [
                new Note(
                    text: sprintf(
                        'median_files_changed absent, fallback to median_lines_changed = %s.',
                        (string) $lines,
                    ),
                    pointer: new Pointer(
                        file: 'git-activity.json',
                        field: 'pull_requests.median_files_changed',
                        value: 'absent',
                    ),
                ),
            ];
        } else {
            return $this->notObservable();
        }

        $level = self::levelFor($band);

        return $this->withSampleConfidence($level, $signalEvidence, $notes, $gitActivity);
    }

    /**
     * @param list<Note> $fallbackNotes
     */
    private function withSampleConfidence(
        Level $level,
        Evidence $signalEvidence,
        array $fallbackNotes,
        GitActivity $gitActivity,
    ): AxisVerdict {
        $total = $gitActivity->pullRequestsTotal ?? 0;

        if ($total >= SampleFloors::MIN_PR_SAMPLE) {
            return new AxisVerdict(
                axis: Axis::Size,
                level: $level,
                confidence: new Confirmed(),
                evidences: [$signalEvidence],
                notes: [
                    ...$fallbackNotes,
                    new Note(
                        text: sprintf(
                            'pull_requests.total = %d (sufficient sample, floor %d).',
                            $total,
                            SampleFloors::MIN_PR_SAMPLE,
                        ),
                        pointer: new Pointer(
                            file: 'git-activity.json',
                            field: 'pull_requests.total',
                            value: (string) $total,
                        ),
                    ),
                ],
            );
        }

        $missing = SampleFloors::MIN_PR_SAMPLE - $total;

        return new AxisVerdict(
            axis: Axis::Size,
            level: $level,
            confidence: new Range(floor: $level, ceiling: Level::Gold, missingSample: $missing),
            evidences: [$signalEvidence],
            notes: [
                ...$fallbackNotes,
                new Note(
                    text: sprintf(
                        'pull_requests.total = %d, below the %d floor: %d missing to confirm.',
                        $total,
                        SampleFloors::MIN_PR_SAMPLE,
                        $missing,
                    ),
                    pointer: new Pointer(
                        file: 'git-activity.json',
                        field: 'pull_requests.total',
                        value: (string) $total,
                    ),
                ),
            ],
        );
    }

    private function notObservable(): AxisVerdict
    {
        return new AxisVerdict(
            axis: Axis::Size,
            level: Level::White,
            confidence: new Range(
                floor: Level::White,
                ceiling: Level::Gold,
                missingSample: self::MISSING_SAMPLE_WHEN_NOT_OBSERVABLE,
            ),
            evidences: [],
            notes: [
                new Note(
                    text: 'Size not observable: median_files_changed and median_lines_changed both absent.',
                    pointer: new Pointer(
                        file: 'git-activity.json',
                        field: 'pull_requests.median_files_changed',
                        value: 'absent',
                    ),
                ),
            ],
        );
    }

    /**
     * The plain claim naming the band and the range of grid cells it satisfies
     * (docs/specs/01-axe-taille.md § Correspondance palier → niveau).
     */
    private static function claimFor(SizeBand $band): string
    {
        return match ($band) {
            SizeBand::S => 'S → satisfies Red',
            SizeBand::M => 'M → satisfies Blue',
            SizeBand::L => 'L → satisfies Green to Gold',
            SizeBand::XL => 'XL → satisfies Green to Gold',
        };
    }

    private static function levelFor(SizeBand $band): Level
    {
        return match ($band) {
            SizeBand::S => Level::Red,
            SizeBand::M => Level::Blue,
            SizeBand::L, SizeBand::XL => Level::Gold,
        };
    }
}
