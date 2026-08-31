<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Size;

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
                claim: self::claimForFiles($band, $files),
                pointer: GitActivityPointer::of('pull_requests.median_files_changed', (string) $files),
            );
            $notes = [];
        } elseif (null !== $lines) {
            $band = SizeThresholds::bandForLines($lines);
            $signalEvidence = new Evidence(
                claim: self::claimForLines($band, $lines),
                pointer: GitActivityPointer::of('pull_requests.median_lines_changed', (string) $lines),
            );
            $notes = [
                new Note(
                    text: sprintf('repli sur les lignes : %s.', self::filesWording($files)),
                    pointer: GitActivityPointer::of(
                        'pull_requests.median_files_changed',
                        null === $files ? 'absent' : (string) $files,
                    ),
                ),
                new Note(
                    text: sprintf('repli sur les lignes : median_lines_changed = %s.', (string) $lines),
                    pointer: GitActivityPointer::of('pull_requests.median_lines_changed', (string) $lines),
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
        $confidence = SampleCheck::confidence($total, SampleFloors::MIN_PR_SAMPLE, $level, Level::Gold);

        $sampleNote = $confidence instanceof Range
            ? new Note(
                text: sprintf(
                    'échantillon insuffisant (plancher %d) : pull_requests.total = %d, il manque %d.',
                    SampleFloors::MIN_PR_SAMPLE,
                    $total,
                    $confidence->missingSample,
                ),
                pointer: GitActivityPointer::of('pull_requests.total', (string) $total),
            )
            : new Note(
                text: sprintf(
                    'échantillon suffisant (plancher %d) : pull_requests.total = %d.',
                    SampleFloors::MIN_PR_SAMPLE,
                    $total,
                ),
                pointer: GitActivityPointer::of('pull_requests.total', (string) $total),
            );

        return new AxisVerdict(
            axis: Axis::Size,
            level: $level,
            confidence: $confidence,
            evidences: [$signalEvidence],
            notes: [...$fallbackNotes, $sampleNote],
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
                pointer: GitActivityPointer::of(
                    'pull_requests.median_files_changed',
                    null === $files ? 'absent' : (string) $files,
                ),
            ),
            new Note(
                text: 'median_lines_changed absent : fournir git-activity.json › pull_requests.median_lines_changed.',
                pointer: GitActivityPointer::of('pull_requests.median_lines_changed', 'absent'),
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
     * The claim names the band, the value measured and the threshold that bounds it
     * (docs/specs/06-sortie-et-progression.md § 3 — "aucun chiffre nu"): at most two named
     * thresholds, the one of the band reached. Files decide first (§ Signal).
     */
    private static function claimForFiles(SizeBand $band, float $files): string
    {
        $value = self::formatNumber($files);

        return match ($band) {
            SizeBand::S => sprintf(
                "ses PR touchent d'habitude %s fichiers (médiane) : taille S (≤ %d), satisfait Red.",
                $value,
                SizeThresholds::FILES_S_MAX,
            ),
            SizeBand::M => sprintf(
                "ses PR touchent d'habitude %s fichiers (médiane) : taille M (≤ %d), satisfait Blue.",
                $value,
                SizeThresholds::FILES_M_MAX,
            ),
            SizeBand::L => sprintf(
                "ses PR touchent d'habitude %s fichiers (médiane) : taille L (≤ %d), satisfait de Green à Gold.",
                $value,
                SizeThresholds::FILES_L_MAX,
            ),
            SizeBand::XL => sprintf(
                "ses PR touchent d'habitude %s fichiers (médiane) : taille XL (> %d), satisfait de Green à Gold.",
                $value,
                SizeThresholds::FILES_L_MAX,
            ),
        };
    }

    /**
     * Same échelle, on the lines fallback (§ Signal — used only when files is absent or zero).
     */
    private static function claimForLines(SizeBand $band, float $lines): string
    {
        $value = self::formatNumber($lines);

        return match ($band) {
            SizeBand::S => sprintf(
                "ses PR touchent d'habitude %s lignes (médiane) : taille S (≤ %d), satisfait Red.",
                $value,
                SizeThresholds::LINES_S_MAX,
            ),
            SizeBand::M => sprintf(
                "ses PR touchent d'habitude %s lignes (médiane) : taille M (≤ %d), satisfait Blue.",
                $value,
                SizeThresholds::LINES_M_MAX,
            ),
            SizeBand::L => sprintf(
                "ses PR touchent d'habitude %s lignes (médiane) : taille L (≤ %d), satisfait de Green à Gold.",
                $value,
                SizeThresholds::LINES_L_MAX,
            ),
            SizeBand::XL => sprintf(
                "ses PR touchent d'habitude %s lignes (médiane) : taille XL (> %d), satisfait de Green à Gold.",
                $value,
                SizeThresholds::LINES_L_MAX,
            ),
        };
    }

    /**
     * A whole median (e.g. 29.0) renders as "29", a fractional one keeps its decimal — the
     * same rendering `InterventionEvaluator::formatNumber()` uses.
     */
    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
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
