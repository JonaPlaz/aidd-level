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
 * Gesture text is French, word for word from the spec's own table: docs/specs/00-vue-ensemble.md
 * § 4 requires every user-facing string — claims, notes, gestures, exception messages — to be
 * French, the jury's language. Only method/property names and comments stay in English (the
 * project's code language).
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
        "écrire et versionner un fichier mémoire à la racine du dépôt (conventions, "
        ."architecture, ce qu'il ne faut pas toucher) et le tenir à jour à chaque erreur "
        .'répétée';

    private const string HARNESS_GREEN_COPPER =
        'ajouter au moins une règle, un agent ou un hook versionné, et câbler le hook dans '
        ."la configuration pour qu'il s'exécute sans coopération du modèle";

    private const string HARNESS_SILVER_GOLD =
        'ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script, '
        .'sur une commande du projet';

    private const string PARALLELISM_COPPER_PLUS =
        'isoler chaque chantier (worktree ou équivalent) et mener au moins trois fronts en '
        .'même temps, habituellement — après le harness';

    private const string INTERVENTION_BLUE =
        'écrire ce qui est attendu avant de générer (cas limites inclus) pour que les '
        .'corrections après ouverture diminuent';

    private const string INTERVENTION_GREEN_COPPER =
        'tests avant le code et validation de la compréhension avant la première ligne ; '
        .'remonter une correction répétée dans les règles plutôt que dans le code';

    private const string INTERVENTION_SILVER =
        "automatiser la validation (tests, lint, duplication) pour qu'aucune reprise "
        .'humaine ne soit nécessaire après ouverture';

    private const string SIZE_ANY =
        'ne rien décréter : la taille habituelle monte quand le dispositif tient ; voir '
        .'Harness';

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
            "aucun geste pour %s vers %s (docs/specs/06-sortie-et-progression.md § Table des "
            .'gestes ne couvre pas cette combinaison).',
            $axis->name,
            $targetLevel->name,
        ));
    }
}
