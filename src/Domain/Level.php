<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The seven levels of the AIDD grid (docs/specs/00-vue-ensemble.md § 2), White to Gold.
 * The backing integer is the rank used by LevelRule to pick a minimum.
 */
enum Level: int
{
    case White = 0;
    case Red = 1;
    case Blue = 2;
    case Green = 3;
    case Copper = 4;
    case Silver = 5;
    case Gold = 6;

    public function rank(): int
    {
        return $this->value;
    }

    /**
     * Icon + name, never the color alone (colorblind reader, § 06 format de sortie).
     */
    public function label(): string
    {
        return match ($this) {
            self::White => '❖ White',
            self::Red => '🔺 Red',
            self::Blue => '🔹 Blue',
            self::Green => '🟢 Green',
            self::Copper => '🥉 Copper',
            self::Silver => '🥈 Silver',
            self::Gold => '🥇 Gold',
        };
    }

    /**
     * The level immediately above, or null past Gold.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::White => self::Red,
            self::Red => self::Blue,
            self::Blue => self::Green,
            self::Green => self::Copper,
            self::Copper => self::Silver,
            self::Silver => self::Gold,
            self::Gold => null,
        };
    }
}
