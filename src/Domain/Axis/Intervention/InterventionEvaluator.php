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
use AiddLevel\Domain\Profile\PullRequests;
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
 * Corroboration), because it is not monotone with the level across the four supplied profiles;
 * so does `pull-requests.json › commits` (last page), flagged only on a flagrant contradiction.
 */
final class InterventionEvaluator implements AxisEvaluator
{
    private const string ACTIVITY_FILE = 'git-activity.json';
    private const string PULL_REQUESTS_FILE = 'pull-requests.json';
    private const string MEDIAN_FIELD = 'pull_requests.median_correction_commits_after_open';
    private const string TOTAL_FIELD = 'pull_requests.total';
    private const string MERGED_WITHOUT_EDIT_FIELD = 'pull_requests.merged_without_human_edit_after_open';
    private const string LAST_PAGE_COMMITS_FIELD = 'commits (median of last page)';

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
                        self::ACTIVITY_FILE,
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
                claim: $this->claimFor($level),
                pointer: $this->pointer(self::ACTIVITY_FILE, self::MEDIAN_FIELD, self::formatNumber($median)),
            ),
        ];

        if (null !== $total) {
            $evidences[] = new Evidence(
                claim: sprintf(
                    "taille de l'échantillon de PR (plancher %d ; plancher « jamais » %d)",
                    SampleFloors::MIN_PR_SAMPLE,
                    SampleFloors::MIN_PR_SAMPLE_ABSENCE,
                ),
                pointer: $this->pointer(self::ACTIVITY_FILE, self::TOTAL_FIELD, (string) $total),
            );
        }

        $notes = [$this->ceilingNote($activity)];

        $corroboration = $this->corroborationNote($activity);
        if (null !== $corroboration) {
            $notes[] = $corroboration;
        }

        $inconsistency = $this->inconsistencyNote($median, $profile->pullRequests);
        if (null !== $inconsistency) {
            $notes[] = $inconsistency;
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
            ? 'absent'
            : self::formatNumber($activity->medianCorrectionCommitsAfterOpen);

        return $this->note(self::CEILING_NOTE, self::ACTIVITY_FILE, self::MEDIAN_FIELD, $value);
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
            self::ACTIVITY_FILE,
            self::MERGED_WITHOUT_EDIT_FIELD,
            (string) $merged,
        );
    }

    /**
     * `pull-requests.json` is a short, last-page list: its `commits` distribution corroborates
     * the aggregate median's order of magnitude, never decides it
     * (docs/specs/03-axe-intervention.md § Corroboration, jamais décision). Only a flagrant
     * gap — at least `InterventionThresholds::MEDIAN_CONTRADICTION_GAP` commits apart from the
     * aggregate median — is worth a note; a smaller gap is page-to-page noise.
     */
    private function inconsistencyNote(float $aggregateMedian, ?PullRequests $pullRequests): ?Note
    {
        if (null === $pullRequests || [] === $pullRequests->items) {
            return null;
        }

        $lastPageMedian = self::median(array_map(
            static fn ($pullRequest): int => $pullRequest->commits,
            $pullRequests->items,
        ));

        if (abs($lastPageMedian - $aggregateMedian) < InterventionThresholds::MEDIAN_CONTRADICTION_GAP) {
            return null;
        }

        return $this->note(
            sprintf(
                'incohérence : médiane agrégée = %s, médiane des commits de la dernière page = %s '
                    .'(pull-requests.json)',
                self::formatNumber($aggregateMedian),
                self::formatNumber($lastPageMedian),
            ),
            self::PULL_REQUESTS_FILE,
            self::LAST_PAGE_COMMITS_FIELD,
            self::formatNumber($lastPageMedian),
        );
    }

    private function note(string $text, string $file, string $field, string $value): Note
    {
        return new Note($text, $this->pointer($file, $field, $value));
    }

    private function pointer(string $file, string $field, string $value): Pointer
    {
        return new Pointer($file, $field, $value);
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
    }

    /**
     * @param list<int> $values
     */
    private static function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if (0 === $count % 2) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return (float) $values[$middle];
    }
}
