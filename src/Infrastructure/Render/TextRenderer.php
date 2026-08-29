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
use AiddLevel\Domain\Progression\RecommendationPolicy;

/**
 * Renders an `Assessment` as plain text (docs/specs/06-sortie-et-progression.md § Format de
 * sortie) — no Symfony dependency, `render()` returns a plain string that the console command
 * of a later chantier prints as-is. Three distinct shapes, one per `AssessmentStatus`; never
 * the color alone (icon + word, colorblind reader among the readers). Every explanation line
 * quotes a real `Pointer` (`file › field = value`), always on its own physical line: pointer
 * text is never word-wrapped together with the sentence that precedes it, so the `›` marker
 * and the value can never be split across a wrap.
 *
 * `Recommendation::$gesture` is already French (docs/specs/00-vue-ensemble.md § 4: every
 * user-facing string is French) and printed as-is; this renderer does not translate or
 * reformulate it. Axis ordering by causal actionability is decided once, in
 * `RecommendationPolicy::AXIS_ORDER` — this renderer reads that constant rather than keeping
 * its own copy.
 */
final class TextRenderer
{
    /** Sortie tenue sous 100 colonnes (docs/specs/06 § Format de sortie). */
    private const int MAX_WIDTH = 100;

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

        $blocks = [$headerBlock];
        $blocks[] = $this->cappingAxesBlock($assessment);

        $acquired = $this->acquiredBlock($assessment, []);
        if (null !== $acquired) {
            $blocks[] = $acquired;
        }

        return $this->withProgressionAndNotes($blocks, $assessment, $target);
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
            'évalué, confiance basse',
            sprintf('Niveau : entre %s et %s', $this->levelName($floor), $this->levelName($ceiling)),
        ]);

        $blocks = [$headerBlock];
        $blocks[] = $this->cappingAxesBlock($assessment);

        // Every axis whose sample was too small to settle gets its range and missing count
        // named, whether or not it happens to be the one holding the global floor down
        // (docs/specs/06 § Raccord avec les statuts — "fourchette et manque chiffré", not
        // limited to the capping axis).
        $rangedNonCapping = $this->rangedNonCappingAxes($assessment);
        $uncertainty = $this->axisDetailBlock(
            'Incertitude sur les autres axes',
            $rangedNonCapping,
            $this->verdictsByAxis($assessment),
        );
        if (null !== $uncertainty) {
            $blocks[] = $uncertainty;
        }

        $acquired = $this->acquiredBlock($assessment, $rangedNonCapping);
        if (null !== $acquired) {
            $blocks[] = $acquired;
        }

        return $this->withProgressionAndNotes($blocks, $assessment, $floor->next());
    }

    /**
     * @return list<Axis>
     */
    private function rangedNonCappingAxes(Assessment $assessment): array
    {
        $ranged = [];
        foreach ($assessment->verdicts as $verdict) {
            if ($verdict->confidence instanceof Range && !\in_array($verdict->axis, $assessment->cappingAxes, true)) {
                $ranged[] = $verdict->axis;
            }
        }

        return $this->orderedAxes($ranged);
    }

    // -- NotAssessable -----------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function notAssessableBlocks(Assessment $assessment): array
    {
        $header = null !== $assessment->missingPrerequisite
            ? $this->wrapped('⛔ Non évaluable — ', $assessment->missingPrerequisite)
            : '⛔ Non évaluable';

        $identityBlock = implode("\n", [
            'Ce qui a été lu',
            null !== $assessment->identity
                ? sprintf('  identité : %s (%s)', $assessment->identity->id, $assessment->identity->role)
                : "  rien : le dossier ou profile.json n'a pas pu être lu",
        ]);

        $blocks = [$header, $identityBlock];

        if (null !== $assessment->hint) {
            $blocks[] = implode("\n", ['Piste technique', $this->wrapped('  ', $assessment->hint)]);
        }

        $notes = $this->notesBlock($assessment->notes);
        if (null !== $notes) {
            $blocks[] = $notes;
        }

        return $blocks;
    }

    // -- Shared building blocks --------------------------------------------------------------

    /**
     * The tail shared by `evaluatedBlocks()` and `lowConfidenceBlocks()`: the recommendations
     * towards `$target` (when there is one to reach), the next-quest block that follows them,
     * and the notes block — appended in this fixed order regardless of status.
     *
     * @param list<string> $blocks
     *
     * @return list<string>
     */
    private function withProgressionAndNotes(array $blocks, Assessment $assessment, ?Level $target): array
    {
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

    private function cappingAxesBlock(Assessment $assessment): string
    {
        $ordered = $this->orderedAxes($assessment->cappingAxes);
        $names = array_map(fn (Axis $axis): string => $this->axisLabel($axis), $ordered);

        $heading = $this->wrapped(
            "Ce qui a mené là — l'axe qui plafonne : ",
            $this->joinFr($names).(\count($ordered) > 1 ? ' (ex æquo)' : ''),
        );

        $verdictsByAxis = $this->verdictsByAxis($assessment);
        $lines = [$heading];
        foreach ($ordered as $axis) {
            $verdict = $verdictsByAxis[$axis->name] ?? null;
            if (null === $verdict) {
                continue;
            }
            array_push($lines, ...$this->axisDetailLines($axis, $verdict));
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<Axis>                 $axes
     * @param array<string, AxisVerdict> $verdictsByAxis
     */
    private function axisDetailBlock(string $heading, array $axes, array $verdictsByAxis): ?string
    {
        if ([] === $axes) {
            return null;
        }

        $lines = [$heading];
        foreach ($axes as $axis) {
            $verdict = $verdictsByAxis[$axis->name] ?? null;
            if (null === $verdict) {
                continue;
            }
            array_push($lines, ...$this->axisDetailLines($axis, $verdict));
        }

        return implode("\n", $lines);
    }

    /**
     * `axis : claim`, every evidence pointer indented and unwrapped below it, and — when the
     * axis is a `Range` — the confirmed/observed interval and the counted-out missing sample
     * (docs/specs/06 § Raccord avec les statuts).
     *
     * @return list<string>
     */
    private function axisDetailLines(Axis $axis, AxisVerdict $verdict): array
    {
        $headline = $verdict->evidences[0]->claim ?? '';
        $lines = [$this->wrapped(sprintf('  %s : ', $this->axisLabel($axis)), $headline)];

        foreach ($verdict->evidences as $evidence) {
            $lines[] = sprintf('    %s', (string) $evidence->pointer);
        }

        if ($verdict->confidence instanceof Range) {
            $lines[] = $this->wrapped(
                '    fourchette : ',
                sprintf(
                    'entre %s et %s (manque %d PR)',
                    $this->levelName($verdict->confidence->floor),
                    $this->levelName($verdict->confidence->ceiling),
                    $verdict->confidence->missingSample,
                ),
            );
        }

        return $lines;
    }

    /**
     * "Acquis pour X" — every axis that neither caps the floor nor carries a `Range`
     * (already displayed in detail elsewhere), each with its claim and pointer(s)
     * (docs/specs/06 § Cinq règles, rule 4 — a claim is never shown without one).
     *
     * @param list<Axis> $alreadyDetailed
     */
    private function acquiredBlock(Assessment $assessment, array $alreadyDetailed): ?string
    {
        $verdictsByAxis = $this->verdictsByAxis($assessment);

        $acquired = [];
        foreach (Axis::cases() as $axis) {
            if (\in_array($axis, $assessment->cappingAxes, true) || \in_array($axis, $alreadyDetailed, true)) {
                continue;
            }
            if (null !== ($verdictsByAxis[$axis->name] ?? null)) {
                $acquired[] = $axis;
            }
        }

        if ([] === $acquired) {
            return null;
        }

        $target = $assessment->level?->next() ?? $assessment->level;
        \assert(null !== $target);

        return $this->axisDetailBlock(sprintf('Acquis pour %s', $this->levelName($target)), $acquired, $verdictsByAxis);
    }

    private function recommendationsBlock(Assessment $assessment, Level $targetLevel): string
    {
        $verdictsByAxis = $this->verdictsByAxis($assessment);

        $lines = [sprintf("Comment monter d'un cran — vers %s", $this->levelName($targetLevel))];
        foreach ($assessment->recommendations as $index => $recommendation) {
            $lines[] = $this->wrapped(
                sprintf('  %d. %s : ', $index + 1, $this->axisLabel($recommendation->axis)),
                $recommendation->gesture,
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

    /**
     * The next quest names the field the gesture must move (`Recommendation::$proofField`,
     * docs/specs/06 § La preuve attendue) and, when one was already observed, the first
     * `Evidence` pointer for that axis — the state the field is coming from.
     */
    private function nextQuestBlock(Assessment $assessment): ?string
    {
        if ([] === $assessment->recommendations) {
            return null;
        }

        $first = $assessment->recommendations[0];
        $lines = ['Prochaine quête'];
        $lines[] = $this->wrapped(sprintf('  %s : ', $this->axisLabel($first->axis)), $first->gesture.'.');
        $lines[] = sprintf('  champ à faire bouger : %s', $first->proofField);

        $pointer = $this->firstPointerFor($assessment, $first->axis);
        if (null !== $pointer) {
            $lines[] = sprintf('  preuve actuelle : %s', (string) $pointer);
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
            $lines[] = $this->wrapped('  · ', $note->text);
            $lines[] = sprintf('    (%s)', (string) $note->pointer);
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
            RecommendationPolicy::AXIS_ORDER,
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
     * `$prefix`. Never given a pointer to wrap: a pointer is always appended on its own,
     * separate, unwrapped line by the caller, so `›` and the value it precedes can never be
     * split by a line break (docs/specs/06 § Cinq règles, rule 4).
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
