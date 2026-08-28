<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Progression;

use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Level;

/**
 * The fixed gesture table (docs/specs/06-sortie-et-progression.md § Table des gestes): one
 * gesture per (axis, target level), written in advance — no LLM drafting, the wording is
 * looked up, never authored at render time. Size never earns a gesture of its own: the axis
 * is mutable but not actionable (§ Cinq règles, rule 5), so every target level redirects to
 * Harness instead of decreeing a size.
 *
 * Only the (axis, target level) pairs the table names are covered. Two combinations the grid
 * itself rules out are deliberately absent instead of guessed: Intervention plateaus at
 * Silver by construction (docs/specs/03-axe-intervention.md § Gold — jamais, cadrage compris),
 * so "Intervention towards Gold" cannot happen from a correctly built verdict; Parallelism
 * below Copper has no gesture written in the table at all. Both raise instead of inventing
 * text the spec never wrote.
 */
final class RecommendationTable
{
    private const string HARNESS_BLUE =
        'write and version a memory file at the repository root (conventions, architecture, '
        .'what must not be touched) and keep it up to date on every repeated mistake';

    private const string HARNESS_GREEN_COPPER =
        'add at least one versioned rule, agent or hook, and wire the hook into the '
        ."configuration so it runs without the model's cooperation";

    private const string HARNESS_SILVER_GOLD =
        'add a bounded automatic retry (N visible attempts) in CI or a script, on a project '
        .'command';

    private const string PARALLELISM_COPPER_PLUS =
        'isolate every workstream (worktree or equivalent) and usually run at least three '
        .'fronts at once — after the harness';

    private const string INTERVENTION_BLUE =
        'write down what is expected before generating (edge cases included) so corrections '
        .'after opening decrease';

    private const string INTERVENTION_GREEN_COPPER =
        'tests before the code and understanding checked before the first line; push a '
        .'repeated correction up into the rules rather than the code';

    private const string INTERVENTION_SILVER =
        'automate validation (tests, lint, duplication) so no human rework is needed after '
        .'opening';

    private const string SIZE_ANY = 'usual size follows the setup; see Harness';

    public function gestureFor(Axis $axis, Level $targetLevel): string
    {
        return match ($axis) {
            Axis::Size => self::SIZE_ANY,
            Axis::Harness => $this->harnessGesture($targetLevel),
            Axis::Parallelism => $this->parallelismGesture($targetLevel),
            Axis::Intervention => $this->interventionGesture($targetLevel),
        };
    }

    private function harnessGesture(Level $targetLevel): string
    {
        return match ($targetLevel) {
            Level::Blue => self::HARNESS_BLUE,
            Level::Green, Level::Copper => self::HARNESS_GREEN_COPPER,
            Level::Silver, Level::Gold => self::HARNESS_SILVER_GOLD,
            default => throw $this->noGestureFor(Axis::Harness, $targetLevel),
        };
    }

    private function parallelismGesture(Level $targetLevel): string
    {
        return match ($targetLevel) {
            Level::Copper, Level::Silver, Level::Gold => self::PARALLELISM_COPPER_PLUS,
            default => throw $this->noGestureFor(Axis::Parallelism, $targetLevel),
        };
    }

    private function interventionGesture(Level $targetLevel): string
    {
        return match ($targetLevel) {
            Level::Blue => self::INTERVENTION_BLUE,
            Level::Green, Level::Copper => self::INTERVENTION_GREEN_COPPER,
            Level::Silver => self::INTERVENTION_SILVER,
            default => throw $this->noGestureFor(Axis::Intervention, $targetLevel),
        };
    }

    private function noGestureFor(Axis $axis, Level $targetLevel): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf(
            'RecommendationTable has no gesture for %s towards %s (docs/specs/06-sortie-et-progression.md '
            .'§ Table des gestes does not cover this combination).',
            $axis->name,
            $targetLevel->name,
        ));
    }
}
