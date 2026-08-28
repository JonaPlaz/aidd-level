<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Progression;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Progression\RecommendationPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecommendationPolicyTest extends TestCase
{
    #[Test]
    public function ordersRecommendationsByActionabilityRegardlessOfInputOrder(): void
    {
        $policy = new RecommendationPolicy();

        $recommendations = $policy->recommend(
            [],
            [Axis::Size, Axis::Intervention, Axis::Harness, Axis::Parallelism],
            Level::Copper,
        );

        self::assertSame(
            [Axis::Harness, Axis::Parallelism, Axis::Intervention, Axis::Size],
            array_map(static fn ($r) => $r->axis, $recommendations),
        );
    }

    #[Test]
    public function axisOrderIsExposedAsTheSingleSourceOfTruth(): void
    {
        self::assertSame(
            [Axis::Harness, Axis::Parallelism, Axis::Intervention, Axis::Size],
            RecommendationPolicy::AXIS_ORDER,
        );
    }

    #[Test]
    public function onlyRecommendsForAxesThatActuallyCap(): void
    {
        $policy = new RecommendationPolicy();

        $recommendations = $policy->recommend([], [Axis::Harness, Axis::Intervention], Level::Silver);

        self::assertCount(2, $recommendations);
        self::assertSame(Axis::Harness, $recommendations[0]->axis);
        self::assertSame(Axis::Intervention, $recommendations[1]->axis);
        self::assertSame(Level::Silver, $recommendations[0]->targetLevel);
    }

    #[Test]
    public function fallsBackToTheTableDefaultProofFieldWithoutAMatchingVerdict(): void
    {
        $policy = new RecommendationPolicy();

        $recommendations = $policy->recommend([], [Axis::Harness], Level::Blue);

        self::assertSame('context_files.agents_md', $recommendations[0]->proofField);
    }

    #[Test]
    public function sizeProofFieldComesFromWhicheverSignalTheVerdictActuallyUsed(): void
    {
        $policy = new RecommendationPolicy();

        $verdict = new AxisVerdict(
            axis: Axis::Size,
            level: Level::Blue,
            confidence: new Confirmed(),
            evidences: [
                new Evidence(
                    'M (median_lines_changed = 180, repli)',
                    new Pointer('git-activity.json', 'pull_requests.median_lines_changed', '180'),
                ),
            ],
        );

        $recommendations = $policy->recommend([$verdict], [Axis::Size], Level::Green);

        self::assertSame('pull_requests.median_lines_changed', $recommendations[0]->proofField);
    }

    #[Test]
    public function harnessProofFieldTowardsGreenCopperIsWhicheverCounterIsNonNull(): void
    {
        $policy = new RecommendationPolicy();

        $verdict = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::Copper,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('behavior', new Pointer('git-activity.json', 'context_files.agents_md', 'true')),
                new Evidence('règles versionnées', new Pointer('git-activity.json', 'context_files.rules_count', '3')),
            ],
        );

        $recommendations = $policy->recommend([$verdict], [Axis::Harness], Level::Copper);

        self::assertSame('context_files.rules_count', $recommendations[0]->proofField);
    }

    #[Test]
    public function aSignalAbsentVerdictAsksForTheMissingFieldInsteadOfTheTableGesture(): void
    {
        $policy = new RecommendationPolicy();

        $verdict = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::White,
            confidence: new Range(Level::White, Level::Gold, 0),
            evidences: [
                new Evidence('non observable', new Pointer('git-activity.json', 'context_files.agents_md', 'absent')),
            ],
            notes: [
                new Note('champ manquant', new Pointer('git-activity.json', 'context_files.agents_md', 'absent')),
            ],
        );

        $recommendations = $policy->recommend([$verdict], [Axis::Harness], Level::Red);

        self::assertSame('fournir le champ context_files.agents_md', $recommendations[0]->gesture);
        self::assertSame('context_files.agents_md', $recommendations[0]->proofField);
    }

    #[Test]
    public function neverThrowsEvenForTheEdgeCasesTheGridRulesOut(): void
    {
        $policy = new RecommendationPolicy();

        // Intervention plateaus at Silver (docs/specs/03) but can still be the sole capping
        // axis when every other axis reaches Gold — the recommendation names the plateau.
        $recommendations = $policy->recommend([], [Axis::Intervention], Level::Gold);

        self::assertCount(1, $recommendations);
        self::assertStringContainsString('plafonne à Silver', $recommendations[0]->gesture);
    }

    #[Test]
    public function sizeAloneStillProducesARecommendationRedirectedToHarness(): void
    {
        $policy = new RecommendationPolicy();

        $recommendations = $policy->recommend([], [Axis::Size], Level::Blue);

        self::assertCount(1, $recommendations);
        self::assertSame(Axis::Size, $recommendations[0]->axis);
        self::assertStringContainsString('geste renvoyé à Harness', $recommendations[0]->gesture);
    }

    #[Test]
    public function returnsAnEmptyListWhenNoAxisCaps(): void
    {
        $policy = new RecommendationPolicy();

        self::assertSame([], $policy->recommend([], [], Level::Gold));
    }
}
