<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain;

use AiddLevel\Domain\Level;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LevelTest extends TestCase
{
    #[Test]
    public function rankFollowsGridOrderFromWhiteToGold(): void
    {
        self::assertSame(0, Level::White->rank());
        self::assertSame(1, Level::Red->rank());
        self::assertSame(2, Level::Blue->rank());
        self::assertSame(3, Level::Green->rank());
        self::assertSame(4, Level::Copper->rank());
        self::assertSame(5, Level::Silver->rank());
        self::assertSame(6, Level::Gold->rank());
    }

    #[Test]
    public function labelCarriesBothAnIconAndAName(): void
    {
        self::assertSame('❖ White', Level::White->label());
        self::assertSame('🥉 Copper', Level::Copper->label());
        self::assertSame('🥇 Gold', Level::Gold->label());
    }

    #[Test]
    public function nextReturnsTheLevelImmediatelyAbove(): void
    {
        self::assertSame(Level::Red, Level::White->next());
        self::assertSame(Level::Blue, Level::Red->next());
        self::assertSame(Level::Green, Level::Blue->next());
        self::assertSame(Level::Copper, Level::Green->next());
        self::assertSame(Level::Silver, Level::Copper->next());
        self::assertSame(Level::Gold, Level::Silver->next());
    }

    #[Test]
    public function nextIsNullPastGold(): void
    {
        self::assertNull(Level::Gold->next());
    }
}
