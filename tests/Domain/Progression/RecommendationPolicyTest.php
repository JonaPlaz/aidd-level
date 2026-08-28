<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Progression;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Level;
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
            [Axis::Size, Axis::Intervention, Axis::Harness, Axis::Parallelism],
            Level::Copper,
        );

        self::assertSame(
            [Axis::Harness, Axis::Parallelism, Axis::Intervention, Axis::Size],
            array_map(static fn ($r) => $r->axis, $recommendations),
        );
    }

    #[Test]
    public function onlyRecommendsForAxesThatActuallyCap(): void
    {
        $policy = new RecommendationPolicy();

        $recommendations = $policy->recommend([Axis::Harness, Axis::Intervention], Level::Silver);

        self::assertCount(2, $recommendations);
        self::assertSame(Axis::Harness, $recommendations[0]->axis);
        self::assertSame(Axis::Intervention, $recommendations[1]->axis);
        self::assertSame(Level::Silver, $recommendations[0]->targetLevel);
    }

    #[Test]
    public function sizeAloneStillProducesARecommendationRedirectedToHarness(): void
    {
        $policy = new RecommendationPolicy();

        $recommendations = $policy->recommend([Axis::Size], Level::Blue);

        self::assertCount(1, $recommendations);
        self::assertSame(Axis::Size, $recommendations[0]->axis);
        self::assertStringContainsString('see Harness', $recommendations[0]->gesture);
    }

    #[Test]
    public function returnsAnEmptyListWhenNoAxisCaps(): void
    {
        $policy = new RecommendationPolicy();

        self::assertSame([], $policy->recommend([], Level::Gold));
    }
}
