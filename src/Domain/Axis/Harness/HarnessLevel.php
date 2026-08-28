<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Level;

/**
 * The five harness-axis values (docs/specs/02-axe-harness.md § Règle): the grid has five
 * cells even though the source definition only describes three. `Behavior` shares its grid
 * cell with `Level::Copper` (the spec calls it « Green et Copper partagent la cellule »).
 */
enum HarnessLevel
{
    case None;
    case Prompts;
    case ContextEngineering;
    case Behavior;
    case Loops;

    public function toLevel(): Level
    {
        return match ($this) {
            self::None => Level::White,
            self::Prompts => Level::Red,
            self::ContextEngineering => Level::Blue,
            self::Behavior => Level::Copper,
            self::Loops => Level::Gold,
        };
    }
}
