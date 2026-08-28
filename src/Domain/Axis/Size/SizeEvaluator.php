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
 *
 * `Evidence` and `Note` text is rendered to the reader (docs/specs/06-sortie-et-progression.md),
 * so it is written in French; identifiers, field names and pointers stay literal.
 */
final readonly class SizeEvaluator implements AxisEvaluator
{
    /**
     * Signal absent (docs/specs/05-robustesse.md § Signal absent): the missing piece is a
     * field, not a pull-request count, so nothing is counted out — each absent field is
     * named in its own note instead.
     */
    private const int MISSING_SAMPLE_WHEN_SIGNAL_ABSENT = 0;

    public function axis(): Axis
    {
        return Axis::Size;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $gitActivity = $profile->gitActivity;
        $files = $gitActivity->medianFilesChanged;
        $lines = $gitActivity->medianLinesChanged;

        if (null !== $files && $files > SizeThresholds::FILES_SIGNAL_MIN) {
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
                    text: sprintf('repli sur les lignes : %s.', self::filesWording($files)),
                    pointer: new Pointer(
                        file: 'git-activity.json',
                        field: 'pull_requests.median_files_changed',
                        value: null === $files ? 'absent' : (string) $files,
                    ),
                ),
                new Note(
                    text: sprintf('repli sur les lignes : median_lines_changed = %s.', (string) $lines),
                    pointer: new Pointer(
                        file: 'git-activity.json',
                        field: 'pull_requests.median_lines_changed',
                        value: (string) $lines,
                    ),
                ),
            ];
        } else {
            return $this->signalAbsent($files);
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
                            'échantillon suffisant (plancher %d) : pull_requests.total = %d.',
                            SampleFloors::MIN_PR_SAMPLE,
                            $total,
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
                        'échantillon insuffisant (plancher %d) : pull_requests.total = %d, il manque %d.',
                        SampleFloors::MIN_PR_SAMPLE,
                        $total,
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

    /**
     * Both signals absent (docs/specs/05-robustesse.md § Signal absent): not observable at
     * all, distinct from an under-sampled band. `missingSample` is 0 — the gap is a field to
     * provide, not a pull-request count — and each absent field is named in its own note with
     * its own pointer.
     */
    private function signalAbsent(?float $files): AxisVerdict
    {
        $notes = [
            new Note(
                text: sprintf('%s.', self::filesWording($files)),
                pointer: new Pointer(
                    file: 'git-activity.json',
                    field: 'pull_requests.median_files_changed',
                    value: null === $files ? 'absent' : (string) $files,
                ),
            ),
            new Note(
                text: 'median_lines_changed absent : fournir git-activity.json › pull_requests.median_lines_changed.',
                pointer: new Pointer(
                    file: 'git-activity.json',
                    field: 'pull_requests.median_lines_changed',
                    value: 'absent',
                ),
            ),
        ];

        return new AxisVerdict(
            axis: Axis::Size,
            level: Level::White,
            confidence: new Range(
                floor: Level::White,
                ceiling: Level::Gold,
                missingSample: self::MISSING_SAMPLE_WHEN_SIGNAL_ABSENT,
            ),
            evidences: [],
            notes: $notes,
        );
    }

    /**
     * The files-field wording, distinguishing a real zero (present, uninformative — falls
     * back to lines) from a genuinely absent field (missing, needs to be provided). Reused by
     * both the lines-fallback note and the signal-absent note so the two never disagree
     * (Codex review of PR #17: `= 0` is never reported as `absent`).
     */
    private static function filesWording(?float $files): string
    {
        return null === $files
            ? 'median_files_changed absent : fournir git-activity.json › pull_requests.median_files_changed'
            : sprintf('median_files_changed = %s, aucune information de taille', (string) $files);
    }

    /**
     * The plain claim naming the band and the range of grid cells it satisfies
     * (docs/specs/01-axe-taille.md § Correspondance palier → niveau). Rendered to the reader,
     * hence written in French (docs/specs/06-sortie-et-progression.md).
     */
    private static function claimFor(SizeBand $band): string
    {
        return match ($band) {
            SizeBand::S => 'S → satisfait Red',
            SizeBand::M => 'M → satisfait Blue',
            SizeBand::L => 'L → satisfait de Green à Gold',
            SizeBand::XL => 'XL → satisfait de Green à Gold',
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
