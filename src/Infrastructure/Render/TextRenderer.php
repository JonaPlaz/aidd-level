<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Render;

use AiddLevel\Domain\Assessment;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Recommendation;

/**
 * Renders an `Assessment` as plain text (docs/specs/06-sortie-et-progression.md § Format de
 * sortie) — no Symfony dependency, `render()` returns a plain string that the console command
 * of a later chantier prints as-is. Three distinct shapes, one per `AssessmentStatus`; never
 * the color alone (icon + word, colorblind reader among the readers). Every explanation line
 * quotes a real `Pointer` (`file › field = value`); pointer lines are never word-wrapped so
 * the `›` marker and the value stay on one line and remain copy-pasteable, unlike the
 * free-form sentences (gesture, claim, acquired summary) which are wrapped to stay within
 * `MAX_WIDTH` columns.
 *
 * `Recommendation::$gesture` is written in English in the domain table
 * (`AiddLevel\Domain\Progression\RecommendationTable`, the project's code language). The
 * jury reads French output (docs/specs/06 § Format de sortie), so this renderer keeps its
 * own French wording of the very same fixed table — a presentation-layer translation, not a
 * second decision: the axis and target level a `Recommendation` carries are what decide,
 * `frenchGesture()` only chooses how to say the same fixed sentence in French.
 */
final class TextRenderer
{
    /** Sortie tenue sous 100 colonnes (docs/specs/06 § Format de sortie). */
    private const int MAX_WIDTH = 100;

    /**
     * Same causal-actionability order as `RecommendationPolicy` (docs/specs/06 § Cinq
     * règles, rule 5) — kept here too because the renderer needs it to order the "l'axe qui
     * plafonne" headline the same way, independently of how the caller ordered `verdicts`.
     *
     * @var list<Axis>
     */
    private const array ACTIONABILITY_ORDER = [
        Axis::Harness,
        Axis::Parallelism,
        Axis::Intervention,
        Axis::Size,
    ];

    public function render(Assessment $assessment): string
    {
        $blocks = match ($assessment->status) {
            AssessmentStatus::Evaluated => $this->evaluatedBlocks($assessment),
            AssessmentStatus::LowConfidence => $this->lowConfidenceBlocks($assessment),
            AssessmentStatus::NotAssessable => $this->notAssessableBlocks($assessment),
        };

        return implode("\n\n", array_filter($blocks, static fn (string $block): bool => '' !== $block))."\n";
    }

    // -- Evaluated -----------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function evaluatedBlocks(Assessment $assessment): array
    {
        $level = $assessment->level;
        \assert(null !== $level);
        $target = $level->next();

        $headerBlock = implode("\n", [
            $this->levelBar($level),
            $this->blockingAxisLine($assessment->cappingAxes),
            $this->header($assessment, $level->label()),
            null !== $target
                ? sprintf('Niveau atteint : %s · niveau visé : %s', $this->levelName($level), $this->levelName($target))
                : sprintf('Niveau atteint : %s · niveau visé : déjà au maximum', $this->levelName($level)),
        ]);

        $blocks = [$headerBlock, $this->cappingAxesBlock($assessment)];

        if (null !== $target) {
            $blocks[] = $this->recommendationsBlock($assessment, $target);
            $quest = $this->nextQuestBlock($assessment);
            if (null !== $quest) {
                $blocks[] = $quest;
            }
        }

        $notes = $this->notesBlock($assessment->notes);
        if (null !== $notes) {
            $blocks[] = $notes;
        }

        return $blocks;
    }

    // -- LowConfidence ---------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function lowConfidenceBlocks(Assessment $assessment): array
    {
        $floor = $assessment->level;
        $ceiling = $assessment->ceiling;
        \assert(null !== $floor && null !== $ceiling);

        $headerBlock = implode("\n", [
            $this->levelRangeBar($floor, $ceiling),
            $this->blockingAxisLine($assessment->cappingAxes),
            $this->header($assessment, sprintf('%s – %s', $floor->label(), $ceiling->label())),
            sprintf('Niveau : entre %s et %s', $this->levelName($floor), $this->levelName($ceiling)),
        ]);

        $blocks = [$headerBlock, $this->cappingAxesBlock($assessment)];

        $target = $floor->next();
        if (null !== $target) {
            $blocks[] = $this->lowConfidenceRecommendationsBlock($assessment, $target);
            $quest = $this->nextQuestBlock($assessment);
            if (null !== $quest) {
                $blocks[] = $quest;
            }
        }

        $notes = $this->notesBlock($assessment->notes);
        if (null !== $notes) {
            $blocks[] = $notes;
        }

        return $blocks;
    }

    private function lowConfidenceRecommendationsBlock(Assessment $assessment, Level $targetLevel): string
    {
        $verdictsByAxis = $this->verdictsByAxis($assessment);

        $lines = [sprintf("Comment monter d'un cran — vers %s", $this->levelName($targetLevel))];
        foreach ($assessment->recommendations as $index => $recommendation) {
            $lines[] = $this->wrapped(
                sprintf('  %d. %s : ', $index + 1, $this->axisLabel($recommendation->axis)),
                $this->frenchGesture($recommendation),
            );

            $verdict = $verdictsByAxis[$recommendation->axis->name] ?? null;
            if (null !== $verdict && $verdict->confidence instanceof Range) {
                $lines[] = $this->wrapped(
                    '     pour lever le doute : ',
                    sprintf('%d PR de plus (échantillon insuffisant)', $verdict->confidence->missingSample),
                );
            }
        }

        return implode("\n", $lines);
    }

    // -- NotAssessable -----------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function notAssessableBlocks(Assessment $assessment): array
    {
        // Assessment carries no dedicated field for "missing prerequisite" / "technical lead"
        // (docs/specs/05-robustesse.md § Trois statuts de sortie): the only free-text channel
        // available on a NotAssessable result is `notes`. Convention adopted here, signalled
        // to the reviewer: notes[0] is the missing prerequisite (its text is the headline,
        // its pointer is the technical lead); any further note is rendered normally below.
        $notes = $assessment->notes;
        $prerequisite = $notes[0] ?? null;

        $header = null !== $prerequisite
            ? $this->wrapped('⛔ Non évaluable — ', $prerequisite->text)
            : '⛔ Non évaluable';

        $identityBlock = implode("\n", [
            'Ce qui a été lu',
            null !== $assessment->identity
                ? sprintf('  identité : %s (%s)', $assessment->identity->id, $assessment->identity->role)
                : "  rien : le dossier ou profile.json n'a pas pu être lu",
        ]);

        $blocks = [$header, $identityBlock];

        if (null !== $prerequisite) {
            $blocks[] = implode("\n", ['Piste technique', sprintf('  %s', (string) $prerequisite->pointer)]);
        }

        $trailing = $this->notesBlock(\array_slice($notes, 1));
        if (null !== $trailing) {
            $blocks[] = $trailing;
        }

        return $blocks;
    }

    // -- Shared building blocks --------------------------------------------------------------

    private function cappingAxesBlock(Assessment $assessment): string
    {
        $ordered = $this->orderedAxes($assessment->cappingAxes);
        $names = array_map(fn (Axis $axis): string => $this->axisLabel($axis), $ordered);

        $lines = [$this->wrapped(
            "Ce qui a mené là — l'axe qui plafonne : ",
            $this->joinFr($names).(\count($ordered) > 1 ? ' (ex æquo)' : ''),
        )];

        $verdictsByAxis = $this->verdictsByAxis($assessment);

        foreach ($ordered as $axis) {
            $verdict = $verdictsByAxis[$axis->name] ?? null;
            if (null === $verdict) {
                continue;
            }

            $headline = $verdict->evidences[0]->claim ?? '';
            $lines[] = $this->wrapped(sprintf('  %s : ', $this->axisLabel($axis)), $headline);
            foreach ($verdict->evidences as $evidence) {
                $lines[] = sprintf('    %s', (string) $evidence->pointer);
            }
        }

        $acquired = [];
        foreach (Axis::cases() as $axis) {
            if (\in_array($axis, $assessment->cappingAxes, true)) {
                continue;
            }

            $verdict = $verdictsByAxis[$axis->name] ?? null;
            if (null === $verdict) {
                continue;
            }

            $claim = $verdict->evidences[0]->claim ?? '';
            $acquired[] = sprintf('%s %s', $this->axisLabel($axis), $claim);
        }

        if ([] !== $acquired) {
            $target = $assessment->level?->next() ?? $assessment->level;
            \assert(null !== $target);
            $lines[] = $this->wrapped(
                sprintf('  Acquis pour %s : ', $this->levelName($target)),
                implode(', ', $acquired),
            );
        }

        return implode("\n", $lines);
    }

    private function recommendationsBlock(Assessment $assessment, Level $targetLevel): string
    {
        $lines = [sprintf("Comment monter d'un cran — vers %s", $this->levelName($targetLevel))];
        foreach ($assessment->recommendations as $index => $recommendation) {
            $lines[] = $this->wrapped(
                sprintf('  %d. %s : ', $index + 1, $this->axisLabel($recommendation->axis)),
                $this->frenchGesture($recommendation),
            );
        }

        return implode("\n", $lines);
    }

    private function nextQuestBlock(Assessment $assessment): ?string
    {
        if ([] === $assessment->recommendations) {
            return null;
        }

        $first = $assessment->recommendations[0];
        $lines = ['Prochaine quête'];
        $lines[] = $this->wrapped(
            sprintf('  %s : ', $this->axisLabel($first->axis)),
            $this->frenchGesture($first).'.',
        );

        $pointer = $this->firstPointerFor($assessment, $first->axis);
        if (null !== $pointer) {
            $lines[] = sprintf('  preuve attendue : %s', (string) $pointer);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<Note> $notes
     */
    private function notesBlock(array $notes): ?string
    {
        if ([] === $notes) {
            return null;
        }

        $lines = ['Notes'];
        foreach ($notes as $note) {
            $lines[] = $this->wrapped('  · ', sprintf('%s (%s)', $note->text, (string) $note->pointer));
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<Axis> $cappingAxes
     */
    private function blockingAxisLine(array $cappingAxes): string
    {
        $ordered = $this->orderedAxes($cappingAxes);
        if ([] === $ordered) {
            return 'axe bloquant : aucun';
        }

        $names = array_map(fn (Axis $axis): string => $this->axisLabel($axis), $ordered);

        return sprintf(
            'axe bloquant : %s%s',
            $this->joinFr($names),
            \count($ordered) > 1 ? ' (ex æquo)' : '',
        );
    }

    // -- Small helpers -------------------------------------------------------------------

    /**
     * @return array<string, AxisVerdict>
     */
    private function verdictsByAxis(Assessment $assessment): array
    {
        $byAxis = [];
        foreach ($assessment->verdicts as $verdict) {
            $byAxis[$verdict->axis->name] = $verdict;
        }

        return $byAxis;
    }

    /**
     * @param list<Axis> $axes
     *
     * @return list<Axis>
     */
    private function orderedAxes(array $axes): array
    {
        return array_values(array_filter(
            self::ACTIONABILITY_ORDER,
            static fn (Axis $axis): bool => \in_array($axis, $axes, true),
        ));
    }

    private function firstPointerFor(Assessment $assessment, Axis $axis): ?Pointer
    {
        foreach ($assessment->verdicts as $verdict) {
            if ($verdict->axis === $axis && [] !== $verdict->evidences) {
                return $verdict->evidences[0]->pointer;
            }
        }

        return null;
    }

    private function header(Assessment $assessment, string $levelLabel): string
    {
        $identity = $assessment->identity;
        if (null === $identity) {
            return $levelLabel;
        }

        return sprintf('%s — %s (%s)', $levelLabel, $identity->id, $identity->role);
    }

    private function axisLabel(Axis $axis): string
    {
        return match ($axis) {
            Axis::Harness => 'Harness',
            Axis::Intervention => 'Intervention',
            Axis::Parallelism => 'En parallèle',
            Axis::Size => 'Taille',
        };
    }

    /**
     * The gesture wording the jury reads, in French, for the same fixed (axis, target level)
     * decision `Recommendation` already carries (docs/specs/06 § Table des gestes). See the
     * class docblock for why this is not the same string as `Recommendation::$gesture`.
     */
    private function frenchGesture(Recommendation $recommendation): string
    {
        return match ($recommendation->axis) {
            Axis::Harness => match ($recommendation->targetLevel) {
                Level::Blue => 'écrire et versionner un fichier mémoire à la racine du dépôt '
                    ."(conventions, architecture, ce qu'il ne faut pas toucher) et le tenir à "
                    .'jour à chaque erreur répétée',
                Level::Green, Level::Copper => 'ajouter au moins une règle, un agent ou un hook '
                    .'versionné, et câbler le hook dans la configuration pour qu\'il s\'exécute '
                    .'sans coopération du modèle',
                default => 'ajouter une relance automatique bornée (N essais visibles) dans la '
                    .'CI ou un script, sur une commande du projet',
            },
            Axis::Parallelism => 'isoler chaque chantier (worktree ou équivalent) et mener au '
                .'moins trois fronts en même temps, habituellement — après le harness',
            Axis::Intervention => match ($recommendation->targetLevel) {
                Level::Blue => "écrire ce qui est attendu avant de générer (cas limites inclus) "
                    .'pour que les corrections après ouverture diminuent',
                Level::Green, Level::Copper => 'tests avant le code et validation de la '
                    .'compréhension avant la première ligne ; remonter une correction répétée '
                    .'dans les règles plutôt que dans le code',
                default => 'automatiser la validation (tests, lint, duplication) pour '
                    ."qu'aucune reprise humaine ne soit nécessaire après ouverture",
            },
            Axis::Size => 'ne rien décréter : la taille habituelle monte quand le dispositif '
                .'tient ; voir Harness',
        };
    }

    private function levelName(Level $level): string
    {
        $label = $level->label();

        return trim(substr($label, (int) strpos($label, ' ')));
    }

    private function levelIcon(Level $level): string
    {
        $label = $level->label();

        return substr($label, 0, (int) strpos($label, ' '));
    }

    private function levelBar(Level $marked): string
    {
        $parts = [];
        foreach (Level::cases() as $level) {
            $icon = $this->levelIcon($level);
            $parts[] = $level === $marked ? "[{$icon}]" : $icon;
        }

        return implode(' ', $parts);
    }

    private function levelRangeBar(Level $floor, Level $ceiling): string
    {
        $parts = [];
        foreach (Level::cases() as $level) {
            $icon = $this->levelIcon($level);
            $parts[] = match (true) {
                $level === $floor && $level === $ceiling => "[{$icon}]",
                $level === $floor => "[{$icon}",
                $level === $ceiling => "{$icon}]",
                default => $icon,
            };
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<string> $items
     */
    private function joinFr(array $items): string
    {
        if (\count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' et '.$last;
    }

    /**
     * Word-wraps `$text` to `MAX_WIDTH` columns, indenting every continuation line under
     * `$prefix`. Never used on a pointer line: a pointer must stay on one line so `›` and the
     * value it precedes are always readable together (docs/specs/06 § Cinq règles, rule 4).
     */
    private function wrapped(string $prefix, string $text): string
    {
        $indent = str_repeat(' ', mb_strlen($prefix));
        $available = max(self::MAX_WIDTH - mb_strlen($prefix), 20);

        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = '' === $current ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $available && '' !== $current) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ('' !== $current || [] === $lines) {
            $lines[] = $current;
        }

        $result = $prefix.array_shift($lines);
        foreach ($lines as $line) {
            $result .= "\n".$indent.$line;
        }

        return $result;
    }
}
