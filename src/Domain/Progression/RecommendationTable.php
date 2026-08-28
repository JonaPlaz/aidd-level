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
 * Completed 2026-08-29 (Codex review on PR #22): the table now covers every (axis, target
 * level) pair the minimum rule can produce, White through Gold — it never throws. Intervention
 * plateaus at Silver by construction (docs/specs/03-axe-intervention.md § Gold — jamais,
 * cadrage compris): towards Gold it still returns text, naming the plateau instead of
 * inventing a task.
 *
 * `proofFieldFor()` returns the field that must move to validate the gesture (§ La preuve
 * attendue) — a bare field name, not a `Pointer` (nothing has been observed there yet).
 */
final class RecommendationTable
{
    private const string HARNESS_RED =
        'commencer à produire avec l\'IA sur de vraies tâches et signer ses commits '
        .'(`Co-Authored-By`) — c\'est le premier fait mesurable';

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

    private const string PARALLELISM_RED_TO_GREEN =
        "mener un chantier avec l'IA jusqu'au merge ; un seul suffit jusqu'à Green";

    private const string PARALLELISM_COPPER_PLUS =
        'isoler chaque chantier (worktree ou équivalent) et mener au moins trois fronts en '
        .'même temps, habituellement — après le harness';

    private const string INTERVENTION_RED =
        'même geste que Harness → Red : rien à reprendre tant que rien n\'est produit avec '
        ."l'IA";

    private const string INTERVENTION_BLUE =
        'écrire ce qui est attendu avant de générer (cas limites inclus) pour que les '
        .'corrections après ouverture diminuent';

    private const string INTERVENTION_GREEN_COPPER =
        'tests avant le code et validation de la compréhension avant la première ligne ; '
        .'remonter une correction répétée dans les règles plutôt que dans le code';

    private const string INTERVENTION_SILVER =
        "automatiser la validation (tests, lint, duplication) pour qu'aucune reprise "
        .'humaine ne soit nécessaire après ouverture';

    private const string INTERVENTION_GOLD =
        "aucun geste : l'axe plafonne à Silver par construction";

    private const string SIZE_ANY =
        'ne rien décréter : la taille habituelle monte quand le dispositif tient ; geste '
        .'renvoyé à Harness';

    public function gestureFor(Axis $axis, Level $targetLevel): string
    {
        return match ($axis) {
            Axis::Size => self::SIZE_ANY,
            Axis::Harness => $this->harnessGesture($targetLevel),
            Axis::Parallelism => $this->parallelismGesture($targetLevel),
            Axis::Intervention => $this->interventionGesture($targetLevel),
        };
    }

    /**
     * The default field that must move to validate the gesture (docs/specs/06 § La preuve
     * attendue), keyed by the exact JSON identifiers the spec's table names. This is only the
     * default: for Taille, the field that actually decided (files, or lines on repli) and, for
     * Harness → Green/Copper, whichever of the four counters is non-null, both come from the
     * verdict itself, not this table — `RecommendationPolicy` looks at the verdict first and
     * falls back to this default only when it cannot.
     */
    public function proofFieldFor(Axis $axis, Level $targetLevel): string
    {
        return match ($axis) {
            Axis::Size => 'pull_requests.median_files_changed',
            Axis::Parallelism => 'parallelism.median_concurrent_branches',
            Axis::Intervention => 'pull_requests.median_correction_commits_after_open',
            Axis::Harness => match ($targetLevel) {
                Level::White, Level::Red => 'commits.ai_coauthored_ratio',
                Level::Blue => 'context_files.agents_md',
                Level::Green, Level::Copper => 'context_files.rules_count, skills_count, hooks_count, agents_count',
                default => 'repo-context/ › bounded retry',
            },
        };
    }

    private function harnessGesture(Level $targetLevel): string
    {
        return match ($targetLevel) {
            Level::White, Level::Red => self::HARNESS_RED,
            Level::Blue => self::HARNESS_BLUE,
            Level::Green, Level::Copper => self::HARNESS_GREEN_COPPER,
            Level::Silver, Level::Gold => self::HARNESS_SILVER_GOLD,
        };
    }

    private function parallelismGesture(Level $targetLevel): string
    {
        return match ($targetLevel) {
            Level::White, Level::Red, Level::Blue, Level::Green => self::PARALLELISM_RED_TO_GREEN,
            Level::Copper, Level::Silver, Level::Gold => self::PARALLELISM_COPPER_PLUS,
        };
    }

    private function interventionGesture(Level $targetLevel): string
    {
        return match ($targetLevel) {
            Level::White, Level::Red => self::INTERVENTION_RED,
            Level::Blue => self::INTERVENTION_BLUE,
            Level::Green, Level::Copper => self::INTERVENTION_GREEN_COPPER,
            Level::Silver => self::INTERVENTION_SILVER,
            Level::Gold => self::INTERVENTION_GOLD,
        };
    }
}
