<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Progression;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Recommendation;

/**
 * Orders the capping axes by causal actionability, never by grid column
 * (docs/specs/06-sortie-et-progression.md § Cinq règles, rule 5: Harness first — actionable
 * today; Parallelism second — actionable but conditioned by Harness; Intervention third —
 * mutable, not actionable directly; Size last — mutable, not actionable, never its own
 * gesture). One `Recommendation` per capping axis.
 *
 * The verdict is looked at before the fixed table (docs/specs/06 § Signal absent d'abord and
 * § La preuve attendue):
 *
 * - a signal-absent verdict (`Range` floored at White, `missingSample = 0`, docs/specs/05 §
 *   Signal absent) never gets the table's gesture — the recommendation is "fournir le champ
 *   <x>", addressed to whoever assembles the profile dossier, not to the developer;
 * - otherwise the gesture comes from `RecommendationTable`, and so does the proof field —
 *   except for Taille (the field that decided: files, or lines on repli) and Harness towards
 *   Green/Copper (whichever counter is non-null), both read from the verdict's own evidence,
 *   the table only providing a default when the verdict cannot say.
 *
 * `AXIS_ORDER` is public so any other consumer of this ordering (currently
 * `AiddLevel\Infrastructure\Render\TextRenderer`, for the "l'axe qui plafonne" headline) reads
 * the same single source instead of keeping its own copy of the same product rule.
 */
final class RecommendationPolicy
{
    /** @var list<Axis> */
    public const array AXIS_ORDER = [
        Axis::Harness,
        Axis::Parallelism,
        Axis::Intervention,
        Axis::Size,
    ];

    /** Harness buckets whose default proof field the verdict can refine (§ La preuve attendue). */
    private const array HARNESS_COUNTER_LEVELS = [Level::Green, Level::Copper];

    public function __construct(
        private readonly RecommendationTable $table = new RecommendationTable(),
    ) {
    }

    /**
     * @param list<AxisVerdict> $verdicts
     * @param list<Axis>        $cappingAxes
     *
     * @return list<Recommendation>
     */
    public function recommend(array $verdicts, array $cappingAxes, Level $targetLevel): array
    {
        $verdictsByAxis = [];
        foreach ($verdicts as $verdict) {
            $verdictsByAxis[$verdict->axis->name] = $verdict;
        }

        $recommendations = [];
        foreach (self::AXIS_ORDER as $axis) {
            if (!\in_array($axis, $cappingAxes, true)) {
                continue;
            }

            $verdict = $verdictsByAxis[$axis->name] ?? null;
            $recommendations[] = null !== $verdict && $this->isSignalAbsent($verdict)
                ? $this->missingFieldRecommendation($axis, $targetLevel, $verdict)
                : new Recommendation(
                    $axis,
                    $targetLevel,
                    $this->table->gestureFor($axis, $targetLevel),
                    $this->proofFieldFor($axis, $targetLevel, $verdict),
                );
        }

        return $recommendations;
    }

    /**
     * docs/specs/05-robustesse.md § Signal absent — distinct de l'échantillon court: the field
     * that decides the axis is missing from git-activity.json, not merely a short sample.
     */
    private function isSignalAbsent(AxisVerdict $verdict): bool
    {
        return $verdict->confidence instanceof Range
            && Level::White === $verdict->confidence->floor
            && 0 === $verdict->confidence->missingSample;
    }

    private function missingFieldRecommendation(Axis $axis, Level $targetLevel, AxisVerdict $verdict): Recommendation
    {
        $field = $this->missingFieldFor($verdict) ?? $this->table->proofFieldFor($axis, $targetLevel);

        return new Recommendation($axis, $targetLevel, sprintf('fournir le champ %s', $field), $field);
    }

    private function missingFieldFor(AxisVerdict $verdict): ?string
    {
        foreach ($verdict->notes as $note) {
            if ('absent' === $note->pointer->value) {
                return $note->pointer->field;
            }
        }

        return null;
    }

    private function proofFieldFor(Axis $axis, Level $targetLevel, ?AxisVerdict $verdict): string
    {
        if (null === $verdict || [] === $verdict->evidences) {
            return $this->table->proofFieldFor($axis, $targetLevel);
        }

        if (Axis::Size === $axis) {
            // docs/specs/06 § La preuve attendue: "le signal qui a décidé" — the verdict's own
            // headline evidence already cites whichever field (files, or lines on repli)
            // decided the band.
            return $verdict->evidences[0]->pointer->field;
        }

        if (Axis::Harness === $axis && \in_array($targetLevel, self::HARNESS_COUNTER_LEVELS, true)) {
            foreach ($verdict->evidences as $evidence) {
                if (str_contains($evidence->pointer->field, '_count')) {
                    return $evidence->pointer->field;
                }
            }
        }

        return $this->table->proofFieldFor($axis, $targetLevel);
    }
}
