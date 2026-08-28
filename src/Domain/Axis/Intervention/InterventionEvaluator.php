<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Intervention;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confidence;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Threshold\InterventionThresholds;
use AiddLevel\Domain\Threshold\SampleFloors;

/**
 * Intervention axis (docs/specs/03-axe-intervention.md): how often a human corrects the AI's
 * work after a pull request opens, read from `git-activity.json ›
 * pull_requests.median_correction_commits_after_open`. The median decides, never the maximum
 * (docs/specs/00-vue-ensemble.md § 5, rule 5).
 *
 * The pull-request sample size gates how sure the verdict is: "never" (Silver) needs its own,
 * higher floor (`SampleFloors::MIN_PR_SAMPLE_ABSENCE`) because it is the only claim of absence
 * a single counter-example would refute; every other median only needs the general floor
 * (`SampleFloors::MIN_PR_SAMPLE`). The axis caps at Silver by construction — no piece supplied
 * distinguishes a human framing a task from an agent framing it — said in a systematic note.
 * `merged_without_human_edit_after_open` corroborates but never decides (docs/specs/03 §
 * Corroboration), because it is not monotone with the level across the four supplied profiles.
 */
final class InterventionEvaluator implements AxisEvaluator
{
    private const string SOURCE_FILE = 'git-activity.json';
    private const string MEDIAN_FIELD = 'pull_requests.median_correction_commits_after_open';
    private const string TOTAL_FIELD = 'pull_requests.total';
    private const string MERGED_WITHOUT_EDIT_FIELD = 'pull_requests.merged_without_human_edit_after_open';

    // Grid wording (docs/specs/00-vue-ensemble.md § 2 and the issue that cites it), one claim
    // per level this axis can reach from the signal alone.
    private const string CLAIM_MAJORITY = 'after the fact, on most PRs';
    private const string CLAIM_PARTIAL = 'after the fact, on a part';
    private const string CLAIM_KEY_STEPS = 'at key steps';
    private const string CLAIM_NEVER = 'never, once framed';

    // The axis cannot observe whether framing itself is automated (docs/specs/03-axe-
    // intervention.md § Gold): Gold is out of reach from this signal alone, whatever the
    // median and the sample say, so every verdict carries this note.
    private const string CEILING_NOTE = 'Gold on this axis would require proof that framing '
        .'itself is automated; not observable in the provided pieces.';

    public function axis(): Axis
    {
        return Axis::Intervention;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $activity = $profile->gitActivity;
        $median = $activity->medianCorrectionCommitsAfterOpen;

        if (null === $median) {
            return new AxisVerdict(
                axis: $this->axis(),
                level: Level::White,
                confidence: new Range(Level::White, Level::Silver, SampleFloors::MIN_PR_SAMPLE),
                evidences: [],
                notes: [
                    $this->note(
                        'no correction-commit signal for this axis',
                        self::MEDIAN_FIELD,
                        'null',
                    ),
                    $this->ceilingNote($activity),
                ],
            );
        }

        $level = InterventionThresholds::levelForMedian($median);
        $total = $activity->pullRequestsTotal;
        $confidence = $this->confidence($level, $total);

        $evidences = [
            new Evidence(
                claim: $this->claimFor($level),
                pointer: $this->pointer(self::MEDIAN_FIELD, self::formatNumber($median)),
            ),
        ];

        if (null !== $total) {
            $evidences[] = new Evidence(
                claim: sprintf(
                    'pull-request sample size (floor %d; floor for "never" %d)',
                    SampleFloors::MIN_PR_SAMPLE,
                    SampleFloors::MIN_PR_SAMPLE_ABSENCE,
                ),
                pointer: $this->pointer(self::TOTAL_FIELD, (string) $total),
            );
        }

        $notes = [$this->ceilingNote($activity)];
        $corroboration = $this->corroborationNote($activity, $total);
        if (null !== $corroboration) {
            $notes[] = $corroboration;
        }

        return new AxisVerdict(
            axis: $this->axis(),
            level: $confidence instanceof Range ? $confidence->floor : $level,
            confidence: $confidence,
            evidences: $evidences,
            notes: $notes,
        );
    }

    /**
     * "Never" (Silver) is the only claim of absence: it needs the higher
     * MIN_PR_SAMPLE_ABSENCE floor, or it turns into a [Copper, Silver] range with the missing
     * sample counted out. Every other median only needs MIN_PR_SAMPLE
     * (docs/specs/03-axe-intervention.md § Seuils).
     */
    private function confidence(Level $level, ?int $total): Confidence
    {
        $sample = $total ?? 0;

        if (Level::Silver === $level) {
            if ($sample >= SampleFloors::MIN_PR_SAMPLE_ABSENCE) {
                return new Confirmed();
            }

            return new Range(Level::Copper, Level::Silver, SampleFloors::MIN_PR_SAMPLE_ABSENCE - $sample);
        }

        if ($sample >= SampleFloors::MIN_PR_SAMPLE) {
            return new Confirmed();
        }

        return new Range($level, Level::Silver, SampleFloors::MIN_PR_SAMPLE - $sample);
    }

    private function claimFor(Level $level): string
    {
        return match ($level) {
            Level::Red => self::CLAIM_MAJORITY,
            Level::Blue => self::CLAIM_PARTIAL,
            Level::Copper => self::CLAIM_KEY_STEPS,
            Level::Silver => self::CLAIM_NEVER,
            default => throw new \LogicException(sprintf(
                'InterventionThresholds::levelForMedian() is not expected to return %s.',
                $level->name,
            )),
        };
    }

    private function ceilingNote(GitActivity $activity): Note
    {
        $value = null === $activity->medianCorrectionCommitsAfterOpen
            ? 'null'
            : self::formatNumber($activity->medianCorrectionCommitsAfterOpen);

        return $this->note(self::CEILING_NOTE, self::MEDIAN_FIELD, $value);
    }

    private function corroborationNote(GitActivity $activity, ?int $total): ?Note
    {
        $merged = $activity->mergedWithoutHumanEditAfterOpen;
        if (null === $merged) {
            return null;
        }

        $value = null !== $total ? sprintf('%d/%d', $merged, $total) : (string) $merged;

        return $this->note(
            sprintf('merged_without_human_edit_after_open = %s (corroborates, does not decide)', $value),
            self::MERGED_WITHOUT_EDIT_FIELD,
            $value,
        );
    }

    private function note(string $text, string $field, string $value): Note
    {
        return new Note($text, $this->pointer($field, $value));
    }

    private function pointer(string $field, string $value): Pointer
    {
        return new Pointer(self::SOURCE_FILE, $field, $value);
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
    }
}
