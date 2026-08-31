<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Intervention;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Axis\Support\GitActivityPointer;
use AiddLevel\Domain\Axis\Support\SampleCheck;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confidence;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Threshold\InterventionThresholds;
use AiddLevel\Domain\Threshold\SampleFloors;

/**
 * Intervention axis (docs/specs/03-axe-intervention.md): how often a human corrects the AI's
 * work after a pull request opens, read from `git-activity.json ›
 * pull_requests.median_correction_commits_after_open`. The median decides, never the maximum
 * (docs/specs/00-vue-ensemble.md § 5, rule 5). `Evidence` and `Note` texts are French: they are
 * the report the jury reads, and the grid wording they quote (docs/specs/00, 03, 06) is French.
 * Field names, identifiers and pointers stay as they are in the source pieces.
 *
 * The pull-request sample size gates how sure the verdict is: "never" (Silver) needs its own,
 * higher floor (`SampleFloors::MIN_PR_SAMPLE_ABSENCE`) because it is the only claim of absence
 * a single counter-example would refute; every other median only needs the general floor
 * (`SampleFloors::MIN_PR_SAMPLE`). The axis caps at Silver by construction — no piece supplied
 * distinguishes a human framing a task from an agent framing it — said in a systematic note.
 * `merged_without_human_edit_after_open` corroborates but never decides (docs/specs/03 §
 * Corroboration), because it is not monotone with the level across the four supplied profiles.
 * `pull-requests.json › commits` is deliberately not compared to the aggregate median: it
 * counts commits per PR, `median_correction_commits_after_open` counts correction commits
 * after opening — different fields, not comparable (docs/specs/03-axe-intervention.md §
 * Corroboration).
 *
 * A median exactly on one of the two one-point discontinuities (`MEDIAN_MAJORITY_MIN`,
 * `MEDIAN_PARTIAL`) gets a note naming the border and the level just below it: the level does
 * not change, but the verdict's margin is nil and the sample rule (§ Median on the border)
 * still owns the status alone.
 */
final class InterventionEvaluator implements AxisEvaluator
{
    private const string MEDIAN_FIELD = 'pull_requests.median_correction_commits_after_open';
    private const string TOTAL_FIELD = 'pull_requests.total';
    private const string MERGED_WITHOUT_EDIT_FIELD = 'pull_requests.merged_without_human_edit_after_open';

    // Grid wording (docs/specs/00-vue-ensemble.md § 2, docs/specs/06 § Format de sortie), one
    // claim per level this axis can reach from the signal alone.
    private const string CLAIM_MAJORITY = 'après coup, sur la majorité';
    private const string CLAIM_PARTIAL = 'après coup, sur une partie';
    private const string CLAIM_KEY_STEPS = 'aux étapes clés';
    private const string CLAIM_NEVER = 'jamais, une fois la tâche cadrée';

    // The axis cannot observe whether framing itself is automated (docs/specs/03-axe-
    // intervention.md § Gold): Gold is out of reach from this signal alone, whatever the
    // median and the sample say, so every verdict carries this note.
    private const string CEILING_NOTE = 'Gold sur cet axe demanderait la preuve que le cadrage '
        .'lui-même est automatisé ; non observable dans les pièces fournies.';

    public function axis(): Axis
    {
        return Axis::Intervention;
    }

    public function evaluate(Profile $profile): AxisVerdict
    {
        $activity = $profile->gitActivity;
        $median = $activity->medianCorrectionCommitsAfterOpen;

        if (null === $median) {
            // docs/specs/05-robustesse.md § Signal absent: the missing piece is the field
            // itself, not a pull-request count, so the confidence Range carries no missing
            // sample size (0) — naming what to supply is the note's job, not a number.
            return new AxisVerdict(
                axis: $this->axis(),
                level: Level::White,
                confidence: new Range(Level::White, Level::Silver, 0),
                evidences: [],
                notes: [
                    $this->note(
                        'aucun signal de commits correctifs après ouverture pour cet axe : '
                            .'fournir pull_requests.median_correction_commits_after_open',
                        self::MEDIAN_FIELD,
                        'absent',
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
                claim: $this->claimFor($level, $median),
                pointer: GitActivityPointer::of(self::MEDIAN_FIELD, self::formatNumber($median)),
            ),
        ];

        if (null !== $total) {
            $evidences[] = new Evidence(
                claim: sprintf(
                    "taille de l'échantillon de PR (plancher %d ; plancher « jamais » %d)",
                    SampleFloors::MIN_PR_SAMPLE,
                    SampleFloors::MIN_PR_SAMPLE_ABSENCE,
                ),
                pointer: GitActivityPointer::of(self::TOTAL_FIELD, (string) $total),
            );
        }

        $notes = [$this->ceilingNote($activity)];

        $borderNote = $this->borderNote($level, $median);
        if (null !== $borderNote) {
            $notes[] = $borderNote;
        }

        $corroboration = $this->corroborationNote($activity);
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
            return SampleCheck::confidence($sample, SampleFloors::MIN_PR_SAMPLE_ABSENCE, Level::Copper, Level::Silver);
        }

        return SampleCheck::confidence($sample, SampleFloors::MIN_PR_SAMPLE, $level, Level::Silver);
    }

    /**
     * The median and the threshold that situates it, at most two named thresholds
     * (docs/specs/06-sortie-et-progression.md § 3 — "aucun chiffre nu"): the one of the level
     * reached, plus — for Blue and Copper only — the one just below, since two thresholds
     * bound those two bands on both sides.
     */
    private function claimFor(Level $level, float $median): string
    {
        $value = self::formatNumber($median);

        return match ($level) {
            Level::Red => sprintf(
                "d'habitude %s corrections après l'ouverture d'une PR (médiane) : %s (≥ %d).",
                $value,
                self::CLAIM_MAJORITY,
                InterventionThresholds::MEDIAN_MAJORITY_MIN,
            ),
            Level::Blue => sprintf(
                "d'habitude %s corrections après l'ouverture d'une PR (médiane) : %s (%d ≤ médiane < %d).",
                $value,
                self::CLAIM_PARTIAL,
                InterventionThresholds::MEDIAN_PARTIAL,
                InterventionThresholds::MEDIAN_MAJORITY_MIN,
            ),
            Level::Copper => sprintf(
                "d'habitude %s corrections après l'ouverture d'une PR (médiane) : %s (%d < médiane < %d).",
                $value,
                self::CLAIM_KEY_STEPS,
                InterventionThresholds::MEDIAN_NEVER,
                InterventionThresholds::MEDIAN_PARTIAL,
            ),
            Level::Silver => sprintf(
                "d'habitude %s correction après l'ouverture d'une PR (médiane) : %s (= %d).",
                $value,
                self::CLAIM_NEVER,
                InterventionThresholds::MEDIAN_NEVER,
            ),
            default => throw new \LogicException(sprintf(
                'InterventionThresholds::levelForMedian() is not expected to return %s.',
                $level->name,
            )),
        };
    }

    /**
     * The median falls exactly on `MEDIAN_MAJORITY_MIN` (Red/Blue) or `MEDIAN_PARTIAL`
     * (Blue/Copper) — the only two one-point discontinuities of this axis
     * (docs/specs/03-axe-intervention.md § Médiane sur la borne). `MEDIAN_KEY_STEPS` (1) is
     * not a border: any strictly positive median is Copper, only 0 opens Silver, so a median
     * of 1 gets no note. The note never touches the status — the sample rule keeps deciding
     * it independently.
     */
    private function borderNote(Level $level, float $median): ?Note
    {
        $levelBelow = match ($median) {
            (float) InterventionThresholds::MEDIAN_MAJORITY_MIN => Level::Blue,
            (float) InterventionThresholds::MEDIAN_PARTIAL => Level::Copper,
            default => null,
        };

        if (null === $levelBelow) {
            return null;
        }

        return $this->note(
            sprintf(
                "médiane %s sur la borne exacte %s/%s ; en dessous, l'axe serait %s",
                self::formatNumber($median),
                $level->name,
                $levelBelow->name,
                $levelBelow->name,
            ),
            self::MEDIAN_FIELD,
            self::formatNumber($median),
        );
    }

    private function ceilingNote(GitActivity $activity): Note
    {
        $value = null === $activity->medianCorrectionCommitsAfterOpen
            ? 'absent'
            : self::formatNumber($activity->medianCorrectionCommitsAfterOpen);

        return $this->note(self::CEILING_NOTE, self::MEDIAN_FIELD, $value);
    }

    /**
     * The pointer carries the raw field value (`merged_without_human_edit_after_open`, an
     * absolute count); the ratio against `pull_requests.total` is derived commentary, so it
     * stays in the note text, never in the pointer (a pointer asserts a value verifiable at
     * that exact field).
     */
    private function corroborationNote(GitActivity $activity): ?Note
    {
        $merged = $activity->mergedWithoutHumanEditAfterOpen;
        if (null === $merged) {
            return null;
        }

        $total = $activity->pullRequestsTotal;
        $ratio = null !== $total ? sprintf('%d/%d', $merged, $total) : (string) $merged;

        return $this->note(
            sprintf('merged_without_human_edit_after_open = %s (corrobore, ne décide pas)', $ratio),
            self::MERGED_WITHOUT_EDIT_FIELD,
            (string) $merged,
        );
    }

    private function note(string $text, string $field, string $value): Note
    {
        return new Note($text, GitActivityPointer::of($field, $value));
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
    }
}
