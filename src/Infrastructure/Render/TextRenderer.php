<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Render;

use AiddLevel\Domain\Assessment;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Progression\RecommendationPolicy;
use AiddLevel\Domain\SourceGlossary;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renders an `Assessment` as plain text (docs/specs/06-sortie-et-progression.md § Format de
 * sortie). `SymfonyStyle`, and nothing more (§ 9): `section()` builds the four titled blocks'
 * heading + dashed underline (§ 9, contrainte 1 — the underline's width is the title's own
 * display width, never `COLUMNS`), every other line is written by hand so the exact grammar
 * of § 2 stays under this class's full control. No helper whose output depends on the
 * terminal width is used (`block()`, `note()`, `warning()`… stay untouched); a pointer line is
 * never wrapped (§ 2, invariant 3) — it is the one line allowed past `MAX_WIDTH`.
 *
 * Two producers write a claim (§ 2, invariant 4): the four `AxisEvaluator`s and
 * `EvaluateProfileHandler::whiteVerdicts()`. This renderer never writes a threshold, a band
 * name or a raw value of its own — it only lays the claim and its pointer out, legend included
 * (§ 4) — and never repeats a fact already rendered once (§ 2, invariant 5).
 */
final class TextRenderer
{
    /** Sortie tenue sous 100 colonnes (docs/specs/06 § 2) — sauf la ligne de pointeur. */
    private const int MAX_WIDTH = 100;

    /**
     * Largeur maximale de la colonne « Constat » du tableau de synthèse (§ 5.4) : bornée pour
     * que la rangée la plus large (bordures + Axe + Niveau + Constat) reste sous MAX_WIDTH —
     * le tableau ne porte aucun pointeur, ses cellules peuvent se replier sans rien couper.
     */
    private const int SYNTHESIS_CLAIM_MAX_WIDTH = 50;

    /** @var array<string, true> keys are legend keys (§ 4), reset at the start of every render(). */
    private array $legendsShown = [];

    /** @var array<string, true> pointer strings already rendered as "ce qui manque" (§ 6.1), reset per render(). */
    private array $consumedPointers = [];

    /**
     * @var array<string, true> "claim|pointer" keys of `Evidence` already rendered once,
     * reset per render() (§ 2, invariant 5) — `whiteVerdicts()` shares the same two `Evidence`
     * across the four axes; without this, a White profile would print them four times.
     */
    private array $renderedEvidence = [];

    public function render(Assessment $assessment): string
    {
        $this->legendsShown = [];
        $this->consumedPointers = [];
        $this->renderedEvidence = [];

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

        $blocks = [$this->header($assessment, $level, $level)];
        $blocks[] = $this->whatLedHereBlock($assessment);

        $target = $level->next();
        $acquired = $this->acquiredBlock($assessment, $target);
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

        $blocks = [$this->header($assessment, $floor, $ceiling)];
        $blocks[] = $this->whatLedHereBlock($assessment);

        $target = $floor->next();
        $acquired = $this->acquiredBlock($assessment, $target);
        if (null !== $acquired) {
            $blocks[] = $acquired;
        }

        return $this->withProgressionAndNotes($blocks, $assessment, $target);
    }

    // -- NotAssessable -----------------------------------------------------------------------

    /**
     * docs/specs/06 § 6.2: no frise, no level, no gesture — nothing manufactured where there
     * is nothing to say. The canonical `non évaluable` label sits on line 1, with the identity
     * when `profile.json` could be read anyway, or bare when it could not (§ 6.2, second
     * precision). Three things follow, in this order (§ 6.1's shape, reused for the gate
     * itself): what is missing (the named prerequisite, its own pointer — the one place a
     * `Pointer` names a piece rather than a field, § 6.2, first precision), what that blocks
     * (the same sentence for every gate failure: nothing downstream is computable without it),
     * and the lead to unblock it.
     *
     * @return list<string>
     */
    private function notAssessableBlocks(Assessment $assessment): array
    {
        $identity = $assessment->identity;
        // A long profile_id or role must not push the identity line past MAX_WIDTH here
        // either (Codex review of PR #72, remark 7).
        $header = null !== $identity
            ? $this->wrapped('⛔ Non évaluable — ', sprintf('%s (%s)', $identity->id, $identity->role))
            : '⛔ Non évaluable';

        $identityBlock = implode("\n", [
            'Ce qui a été lu',
            null !== $identity
                ? $this->wrapped('  identité : ', sprintf('%s (%s)', $identity->id, $identity->role))
                : "  rien : le dossier ou profile.json n'a pas pu être lu",
        ]);

        $blocks = [$header, $identityBlock];

        if (null !== $assessment->missingPrerequisite) {
            $lines = [
                $this->wrapped('Ce qui manque : ', $assessment->missingPrerequisite.'.'),
                $this->wrapped(
                    'Ce que ça empêche : ',
                    'les quatre axes se calculent depuis ce prérequis ; sans lui, aucun niveau '
                    .'ne peut être rendu.',
                ),
            ];
            if (null !== $assessment->hint) {
                $lines[] = $this->wrapped('Pour débloquer : ', $assessment->hint.'.');
            }
            $blocks[] = implode("\n", $lines);
        }

        $notes = $this->notesBlock($assessment->notes);
        if (null !== $notes) {
            $blocks[] = $notes;
        }

        return $blocks;
    }

    // -- Header (§ 5.1) -----------------------------------------------------------------------

    private function header(Assessment $assessment, Level $floor, Level $ceiling): string
    {
        // docs/specs/06 § 6: the canonical status label — "évalué" vs "évalué, confiance
        // basse" — is driven by `Assessment::$status`, never by comparing `$floor` and
        // `$ceiling`: a `Range` masked by a lower `Confirmed` bottleneck can legitimately
        // leave floor === ceiling while `LevelRule` still reports the result unconfirmed
        // (LevelRule::apply() docblock; Codex review of PR #72, remark 1).
        $evaluated = AssessmentStatus::Evaluated === $assessment->status;

        // Ordre d'en-tête (§ 5.1, arbitré par Jonathan le 2026-08-31) : l'identité d'abord,
        // puis le niveau, puis l'échelle nommée — le lecteur rencontre la personne avant le
        // verdict.
        $identityLine = null !== $assessment->identity
            // A long profile_id or role must not push the identity line past MAX_WIDTH — only
            // pointer lines are exempt from wrapping (§ 2, invariant 3; Codex review of PR
            // #72, remark 7).
            ? $this->wrapped('', sprintf('%s — %s', $assessment->identity->id, $assessment->identity->role))
            : null;

        $levelLine = $evaluated
            ? sprintf('Niveau AIDD : %s', $floor->label())
            : sprintf('Niveau AIDD : entre %s et %s', $floor->label(), $ceiling->label());

        $scaleLine = 'Échelle des niveaux : '
            .($evaluated ? $this->levelBar($floor) : $this->levelRangeBar($floor, $ceiling));

        $reliabilityLine = $evaluated
            ? 'Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.'
            : $this->lowConfidenceReliabilityLine($assessment);

        $nextLine = $this->nextLevelLine($floor, $assessment->cappingAxes);

        return implode("\n", array_values(array_filter(
            [$identityLine, $levelLine, $scaleLine, $reliabilityLine, $nextLine],
            static fn (?string $line): bool => null !== $line,
        )));
    }

    /**
     * The seven levels, icon **and** name (docs/specs/06 § 5.1 — a bare icon frise does not
     * read without the grid, and not at all when the terminal drops emoji), the reached level
     * bracketed.
     */
    private function levelBar(Level $marked): string
    {
        $parts = [];
        foreach (Level::cases() as $level) {
            $parts[] = $level === $marked ? sprintf('[%s]', $level->label()) : $level->label();
        }

        return implode('  ', $parts);
    }

    private function levelRangeBar(Level $floor, Level $ceiling): string
    {
        $parts = [];
        foreach (Level::cases() as $level) {
            $token = $level->label();
            $parts[] = match (true) {
                $level === $floor && $level === $ceiling => sprintf('[%s]', $token),
                $level === $floor => sprintf('[%s', $token),
                $level === $ceiling => sprintf('%s]', $token),
                default => $token,
            };
        }

        return implode('  ', $parts);
    }

    /**
     * docs/specs/06 § 6, table: names every axis in a `Range`, not just the one capping the
     * floor (§ 5.4's other half of the same decision).
     */
    private function lowConfidenceReliabilityLine(Assessment $assessment): string
    {
        $parts = [];
        foreach ($this->orderedAxes($this->rangedAxes($assessment)) as $axis) {
            $verdict = $this->verdictsByAxis($assessment)[$axis->name] ?? null;
            if (null === $verdict || !$verdict->confidence instanceof Range) {
                continue;
            }
            $parts[] = sprintf('%s (%s)', $this->axisLabel($axis), $this->missingWording($verdict->confidence));
        }

        return $this->wrapped(
            'Fiabilité : ',
            sprintf('évalué, confiance basse — %s ; le niveau est donné en fourchette.', $this->joinFr($parts)),
        );
    }

    /**
     * The condition of passage (docs/specs/06 § 5.1, table "La condition de passage,
     * exactement"): a single blocking axis, several named one by one plus the minimum-rule
     * sentence, the already-Gold case, and Intervention plateauing Gold structurally out of
     * reach (docs/specs/03-axe-intervention.md § Gold).
     *
     * @param list<Axis> $cappingAxes
     */
    private function nextLevelLine(Level $current, array $cappingAxes): string
    {
        $target = $current->next();

        if (null === $target) {
            return sprintf('Niveau suivant : aucun — %s est le dernier niveau de la grille.', Level::Gold->label());
        }

        if (Level::Gold === $target && \in_array(Axis::Intervention, $cappingAxes, true)) {
            return $this->wrapped(
                'Niveau suivant : ',
                sprintf(
                    "%s — hors d'atteinte ici : l'axe Intervention plafonne à %s, « cadrage "
                    .'compris » ne se constate dans aucune pièce fournie (spec 03).',
                    $target->label(),
                    Level::Silver->label(),
                ),
            );
        }

        $ordered = $this->orderedAxes($cappingAxes);
        $names = array_map(fn (Axis $axis): string => $this->axisLabel($axis), $ordered);

        $axisPhrase = 1 >= \count($ordered)
            ? sprintf('il faut que %s y monte.', $names[0] ?? '')
            : sprintf(
                'il faut que %s y montent%s ; le niveau est le plus bas des quatre axes, un axe '
                ."haut n'en compense pas un bas.",
                $this->joinFr($names),
                2 === \count($ordered) ? ' tous les deux' : ' tous',
            );

        return $this->wrapped('Niveau suivant : ', sprintf('%s — %s', $target->label(), $axisPhrase));
    }

    // -- "Ce qui a mené là" (§ 5.2, § 5.3, § 5.4) ---------------------------------------------

    private function whatLedHereBlock(Assessment $assessment): string
    {
        $lines = [$this->sectionTitle('Ce qui a mené là')];
        $lines[] = $this->synthesisTable($assessment);
        $lines[] = '';

        $verdictsByAxis = $this->verdictsByAxis($assessment);
        $ordered = $this->orderedAxes($assessment->cappingAxes);
        $blockingCount = \count($ordered);

        foreach ($ordered as $index => $axis) {
            if (0 !== $index) {
                $lines[] = '';
            }
            $verdict = $verdictsByAxis[$axis->name] ?? null;
            if (null === $verdict) {
                continue;
            }
            $blockPhrase = 1 === $blockingCount
                ? "l'axe qui bloque"
                : sprintf("l'un des %s axes qui bloquent", $this->frenchNumber($blockingCount));
            $lines[] = sprintf('  %s — %s : %s', $this->axisLabel($axis), $verdict->level->label(), $blockPhrase);
            array_push($lines, ...$this->axisBodyLines($verdict, includeTranche: false));
        }

        return implode("\n", $lines);
    }

    /**
     * docs/specs/06 § 5.4 — one line, every axis, in `RecommendationPolicy::AXIS_ORDER`, a
     * pure recap of the lines rendered right below (excluded from § 12's pointer count on
     * purpose, it is the third named exception to rule 4).
     */
    private function synthesisTable(Assessment $assessment): string
    {
        $verdictsByAxis = $this->verdictsByAxis($assessment);
        $rows = [];
        foreach (RecommendationPolicy::AXIS_ORDER as $axis) {
            $verdict = $verdictsByAxis[$axis->name] ?? null;
            if (null === $verdict) {
                continue;
            }
            $blocking = \in_array($axis, $assessment->cappingAxes, true);
            $ranged = $verdict->confidence instanceof Range;

            $level = $ranged
                ? sprintf('%s–%s', $verdict->level->label(), $verdict->confidence->ceiling->label())
                : $verdict->level->label();

            $mentions = array_filter([$blocking ? 'bloque' : null, $ranged ? 'fourchette' : null]);
            $suffix = [] !== $mentions ? sprintf(' (%s)', implode(', ', $mentions)) : '';

            $rows[] = [
                $this->axisLabel($axis),
                $level.$suffix,
                [] === $verdict->evidences ? '' : $verdict->evidences[0]->claim,
            ];
        }

        // Un vrai tableau aligné (arbitré par Jonathan le 2026-08-31) : le composant Table de
        // symfony/console, sur un BufferedOutput — aucune dépendance à la largeur du terminal.
        // Ses cellules ne portent jamais de pointeur : la colonne « Constat » peut donc se
        // replier (setColumnMaxWidth) sans couper quoi que ce soit de vérifiable.
        $buffer = new BufferedOutput();
        $table = new Table($buffer);
        $table->setHeaders(['Axe', 'Niveau', 'Constat']);
        $table->setRows($rows);
        $table->setColumnMaxWidth(2, self::SYNTHESIS_CLAIM_MAX_WIDTH);
        $table->render();

        $lines = explode("\n", rtrim($buffer->fetch(), "\n"));

        return implode("\n", array_map(static fn (string $line): string => '  '.rtrim($line), $lines));
    }

    /**
     * @return list<Axis>
     */
    private function rangedAxes(Assessment $assessment): array
    {
        $ranged = [];
        foreach ($assessment->verdicts as $verdict) {
            if ($verdict->confidence instanceof Range) {
                $ranged[] = $verdict->axis;
            }
        }

        return $ranged;
    }

    // -- "Déjà acquis pour X" (§ 5.4) ---------------------------------------------------------

    private function acquiredBlock(Assessment $assessment, ?Level $target): ?string
    {
        $verdictsByAxis = $this->verdictsByAxis($assessment);

        $acquired = [];
        foreach (RecommendationPolicy::AXIS_ORDER as $axis) {
            if (\in_array($axis, $assessment->cappingAxes, true)) {
                continue;
            }
            if (null !== ($verdictsByAxis[$axis->name] ?? null)) {
                $acquired[] = $axis;
            }
        }

        if ([] === $acquired) {
            return null;
        }

        $title = null !== $target ? sprintf('Déjà acquis pour %s', $target->label()) : 'Déjà acquis';
        $lines = [$this->sectionTitle($title)];

        foreach ($acquired as $index => $axis) {
            if (0 !== $index) {
                $lines[] = '';
            }
            $verdict = $verdictsByAxis[$axis->name];
            $ranged = $verdict->confidence instanceof Range;
            $lines[] = sprintf(
                '  %s — %s%s',
                $this->axisLabel($axis),
                $verdict->level->label(),
                $ranged ? ' (fourchette)' : '',
            );
            // docs/specs/06 § 5.4, rule "pas de détail": a non-blocking axis only rends the
            // Evidence that decided its level, never the full fact-by-fact detail reserved to
            // a blocking axis (§ 5.2, rule 3 — Codex review of PR #72, remark 5).
            array_push($lines, ...$this->axisBodyLines($verdict, includeTranche: true, onlyDecisiveEvidence: true));
        }

        return implode("\n", $lines);
    }

    // -- Shared axis-detail rendering (§ 2, § 6.1) -------------------------------------------

    /**
     * Every `Evidence` of the axis (§ 2, invariant 1 — never only the first one) when the axis
     * blocks, or only the first (decisive) one when it does not (§ 5.4, "pas de détail" —
     * Codex review of PR #72, remark 5). Then every `Note` this axis's own explanation still
     * owes the reader (§ 12.1) — a missing field (§ 6.1, "ce qui manque"), the applicable
     * pull-request floor when the sample fell short of it (Codex review of PR #72, remark 2:
     * the floor must stay in the axis's own block, not vanish with the generic Notes filter),
     * and any fact structurally decisive for the axis's own level (Codex review of PR #72,
     * remark 8: Harness's "no bounded loop found"/"repo-context/ absent" cap it at Copper,
     * they are not corroboration to discard). Finally, for a `Range` axis, the `fourchette`
     * line (§ 6.1, "ce qui empêche" — plancher et plafond disent jusqu'où). The `pour trancher`
     * line (§ 6.1, "le geste") is only rendered here for an axis that does not block (§ 5.4) —
     * a blocking axis carries it under its gesture instead (§ 5.5).
     *
     * @return list<string>
     */
    private function axisBodyLines(AxisVerdict $verdict, bool $includeTranche, bool $onlyDecisiveEvidence = false): array
    {
        $lines = [];

        $evidences = $onlyDecisiveEvidence ? \array_slice($verdict->evidences, 0, 1) : $verdict->evidences;
        foreach ($evidences as $evidence) {
            $key = $evidence->claim.'|'.(string) $evidence->pointer;
            if (isset($this->renderedEvidence[$key])) {
                continue;
            }
            $this->renderedEvidence[$key] = true;
            array_push($lines, ...$this->evidenceLines($evidence, indent: 4));
        }

        $ranged = $verdict->confidence instanceof Range;
        foreach ($verdict->notes as $note) {
            if ($this->belongsToAxisBody($note, $ranged)) {
                array_push($lines, ...$this->noteLines($note, indent: 4));
            }
        }

        if ($verdict->confidence instanceof Range) {
            $lines[] = $this->wrapped(
                '    fourchette : ',
                sprintf('entre %s et %s', $verdict->confidence->floor->label(), $verdict->confidence->ceiling->label()),
            );

            if ($includeTranche) {
                $lines[] = $this->wrapped('    pour trancher : ', $this->trancheText($verdict->notes, $verdict->confidence));
            }
        }

        return $lines;
    }

    /**
     * Structural selection, never free-text matching (Codex review of PR #72, remark 4: a
     * user-supplied `profile.json › note` could legitimately contain a phrase like "échantillon
     * insuffisant", and a substring check would wrongly swallow it). A note belongs to its
     * axis's own explanation when its `Pointer` identifies it as one of three known kinds: a
     * missing field (§ 6.1), the pull-request sample floor `SampleCheck` names on a `Range`
     * axis (remark 2), or a fact structurally decisive for the level Harness reached (remark 8).
     */
    private function belongsToAxisBody(Note $note, bool $ranged): bool
    {
        if ($this->isDecisiveHarnessNote($note)) {
            return true;
        }

        if (!$ranged) {
            return false;
        }

        return 'absent' === $note->pointer->value || $this->isSampleTotalNote($note);
    }

    /**
     * `pull_requests.total` is the one pointer `SampleCheck`'s floor note always carries
     * (Size, Parallelism) — matched by pointer identity, not by the "suffisant"/"insuffisant"
     * wording (Codex review of PR #72, remarks 2 and 4).
     */
    private function isSampleTotalNote(Note $note): bool
    {
        return 'git-activity.json' === $note->pointer->file && 'pull_requests.total' === $note->pointer->field;
    }

    /**
     * Harness's own "why the axis stops here" facts (docs/specs/02-axe-harness.md § Boucles):
     * no bounded retry found, or `repo-context/` itself absent — both cap the axis at Copper.
     * Matched by the pointer's field, the same structural identifier
     * `RecommendationTable::proofFieldFor()` already uses for `repo-context/ › bounded retry`
     * (Codex review of PR #72, remark 8) — never by the note's free text.
     */
    private function isDecisiveHarnessNote(Note $note): bool
    {
        return 'repo-context/' === $note->pointer->file
            && \in_array($note->pointer->field, ['bounded retry', 'directory'], true);
    }

    /**
     * @return list<string>
     */
    private function evidenceLines(Evidence $evidence, int $indent): array
    {
        $pad = str_repeat(' ', $indent);
        $lines = [$this->wrapped($pad, $evidence->claim)];

        $legend = $this->legendLine($evidence->pointer->file, $indent + 2);
        if (null !== $legend) {
            $lines[] = $legend;
        }

        $lines[] = str_repeat(' ', $indent + 2).(string) $evidence->pointer;
        $this->consumedPointers[(string) $evidence->pointer] = true;

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function noteLines(Note $note, int $indent): array
    {
        $pad = str_repeat(' ', $indent);
        $lines = [$this->wrapped($pad, $note->text)];

        $legend = $this->legendLine($note->pointer->file, $indent + 2);
        if (null !== $legend) {
            $lines[] = $legend;
        }

        $lines[] = str_repeat(' ', $indent + 2).(string) $note->pointer;
        $this->consumedPointers[(string) $note->pointer] = true;

        return $lines;
    }

    /**
     * @param list<Note> $notes
     */
    private function trancheText(array $notes, Range $confidence): string
    {
        if ($confidence->missingSample > 0) {
            return sprintf('%d PR de plus', $confidence->missingSample);
        }

        $fields = [];
        foreach ($notes as $note) {
            if ('absent' === $note->pointer->value) {
                $fields[] = $note->pointer->field;
            }
        }

        return sprintf('fournir le champ %s', $this->joinFr($fields));
    }

    // -- "Comment monter d'un cran" (§ 5.5, § 8) -----------------------------------------------

    private function recommendationsBlock(Assessment $assessment, Level $targetLevel): string
    {
        $verdictsByAxis = $this->verdictsByAxis($assessment);

        $lines = [$this->sectionTitle(sprintf("Comment monter d'un cran — vers %s", $targetLevel->label()))];

        foreach ($assessment->recommendations as $index => $recommendation) {
            if (0 !== $index) {
                $lines[] = '';
            }

            $marker = 0 === $index ? ' (à faire en premier)' : '';
            $lines[] = $this->wrapped(
                sprintf('  %d. %s%s — ', $index + 1, $this->axisLabel($recommendation->axis), $marker),
                $recommendation->gesture,
            );
            $lines[] = $this->wrapped('     Ce qui le prouvera : ', $recommendation->proofField);

            if (0 === $index) {
                $verdictForQuest = $verdictsByAxis[$recommendation->axis->name] ?? null;
                $pointer = null !== $verdictForQuest
                    ? $this->pointerForProofField($verdictForQuest, $recommendation->proofField)
                    : null;
                // docs/specs/06 § 5.5: "Aujourd'hui" is the state the proof field itself is
                // coming from — never an unrelated first Evidence (Codex review of PR #72,
                // remark 3). Said explicitly, never silently omitted, when nothing observed
                // yet matches that exact field.
                $lines[] = null !== $pointer
                    ? sprintf('     Aujourd\'hui : %s', (string) $pointer)
                    : "     Aujourd'hui : aucune preuve pointée pour ce champ pour l'instant.";
            }

            $verdict = $verdictsByAxis[$recommendation->axis->name] ?? null;
            if (null !== $verdict && $verdict->confidence instanceof Range && 0 !== $verdict->confidence->missingSample) {
                $lines[] = $this->wrapped(
                    '     pour trancher : ',
                    sprintf('%d PR de plus (échantillon insuffisant)', $verdict->confidence->missingSample),
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The tail shared by `evaluatedBlocks()` and `lowConfidenceBlocks()`.
     *
     * @param list<string> $blocks
     *
     * @return list<string>
     */
    private function withProgressionAndNotes(array $blocks, Assessment $assessment, ?Level $target): array
    {
        if (null !== $target && [] !== $assessment->recommendations) {
            $blocks[] = $this->recommendationsBlock($assessment, $target);
        }

        $notes = $this->notesBlock($assessment->notes);
        if (null !== $notes) {
            $blocks[] = $notes;
        }

        return $blocks;
    }

    // -- "Notes" (§ 5.6) ------------------------------------------------------------------------

    private const string FAMILY_DISCARDED = 'Écarté du calcul';
    private const string FAMILY_PIECES = 'Pièces du dossier';
    private const string FAMILY_QUALITY = 'Qualité, citée sans jugement';

    /**
     * Three families, deduplicated (docs/specs/06 § 5.6) **by pointer identity only** — never
     * by matching against a note's free text (Codex review of PR #72, remark 4: `profile.json
     * › note` carries whatever the profile wrote, and a substring check on it would risk
     * silently dropping a legitimate remark). A note whose pointer was already consumed while
     * rendering an axis's own body (§ 6.1's "ce qui manque", the sample floor, or a decisive
     * Harness fact — `belongsToAxisBody()`) does not repeat here (§ 2, invariant 5); two notes
     * sharing a pointer fuse into the first. This is also how the Intervention ceiling note
     * disappears without a dedicated rule: its pointer is always identical to the axis's own
     * primary `Evidence` pointer (`InterventionEvaluator::ceilingNote()`, same field and same
     * formatted value), which every axis already renders once, blocking or not (§ 5.4) — so it
     * is always already in `$seenPointers` by the time this runs.
     *
     * @param list<Note> $notes
     */
    private function notesBlock(array $notes): ?string
    {
        $seenPointers = $this->consumedPointers;
        $families = [self::FAMILY_DISCARDED => [], self::FAMILY_PIECES => [], self::FAMILY_QUALITY => []];

        foreach ($notes as $note) {
            if ($this->isSampleTotalNote($note)) {
                // The Confirmed-sample ("échantillon suffisant") case never gets consumed by
                // an axis body (only a Range axis renders a sample-floor note there): the
                // `Fiabilité : évalué` line already says the sample sufficed, structurally,
                // for every axis at once (§ 5.6, rule 1) — dropped by pointer identity, not by
                // matching the word "suffisant" (remark 4).
                continue;
            }

            $key = (string) $note->pointer;
            if (isset($seenPointers[$key])) {
                continue;
            }
            $seenPointers[$key] = true;

            $families[$this->familyFor($note)][] = $note;
        }

        $lines = [];
        foreach ($families as $title => $familyNotes) {
            if ([] === $familyNotes) {
                continue;
            }
            if ([] !== $lines) {
                $lines[] = '';
            }
            $lines[] = $title;
            foreach ($familyNotes as $note) {
                $lines[] = $this->wrapped('  · ', $note->text);
                $legend = $this->legendLine($note->pointer->file, 4);
                if (null !== $legend) {
                    $lines[] = $legend;
                }
                $lines[] = sprintf('    (%s)', (string) $note->pointer);
            }
        }

        if ([] === $lines) {
            return null;
        }

        return implode("\n", [$this->sectionTitle('Notes'), ...$lines]);
    }

    /**
     * Family assignment by pointer identity (docs/specs/06 § 5.6): `sonar-measures.json` is
     * always Qualité; `profile.json › available` (declared-but-absent), `declaratif.md`
     * (declarative-piece presence) and the `présent` value `EvaluateProfileHandler` gives a
     * present-but-undeclared piece are always Pièces du dossier — `présent` is the same
     * literal `EvaluateProfileHandler::availabilityNotes()`/`buildNotes()` write for both, a
     * structural marker rather than the piece's own path (which varies per profile). Everything
     * else — including a profile's own free-text note (`profile.json › note`) — falls back to
     * Écarté du calcul, exactly as § 5.6 prescribes for a note whose family is not determined
     * by its source: a gap in this table, not a text guess (Codex review of PR #72, remark 4).
     */
    private function familyFor(Note $note): string
    {
        if ('sonar-measures.json' === $note->pointer->file) {
            return self::FAMILY_QUALITY;
        }

        if ('available' === $note->pointer->field
            || 'declaratif.md' === $note->pointer->file
            || 'présent' === $note->pointer->value
        ) {
            return self::FAMILY_PIECES;
        }

        return self::FAMILY_DISCARDED;
    }

    // -- Legend (§ 4) ---------------------------------------------------------------------------

    private function legendLine(string $file, int $indent): ?string
    {
        $legend = SourceGlossary::legendFor($file);
        if (null === $legend) {
            return null;
        }

        // The legend is printed under the normalized key, never the full nested path: a
        // `repo-context/…` pointer can be arbitrarily deep, and the legend line — unlike a
        // pointer line — is not exempt from MAX_WIDTH (§ 2, invariant 3; Codex review of
        // PR #72, remark 6).
        $key = str_starts_with($file, 'repo-context/') ? 'repo-context/' : $file;
        if (isset($this->legendsShown[$key])) {
            return null;
        }
        $this->legendsShown[$key] = true;

        return $this->wrapped(str_repeat(' ', $indent).$key.' — ', $legend.'.');
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

    /**
     * The pointer of the `Evidence` or `Note` that already observed
     * `Recommendation::$proofField` for this axis — never just the axis's first `Evidence`
     * (Codex review of PR #72, remark 3). `$proofField` is matched three ways, in the shapes
     * `RecommendationTable`/`RecommendationPolicy` actually produce: the bare dotted field
     * (`context_files.rules_count`), the `file › field` form used as Harness's Silver/Gold
     * default (`repo-context/ › bounded retry`), or a comma-separated list of alternative
     * fields (Harness's Green/Copper default, only reached without a matching verdict).
     */
    private function pointerForProofField(AxisVerdict $verdict, string $proofField): ?Pointer
    {
        $candidates = [];
        foreach ($verdict->evidences as $evidence) {
            $candidates[] = $evidence->pointer;
        }
        foreach ($verdict->notes as $note) {
            $candidates[] = $note->pointer;
        }

        foreach ($candidates as $pointer) {
            if ($pointer->field === $proofField || sprintf('%s › %s', $pointer->file, $pointer->field) === $proofField) {
                return $pointer;
            }
        }

        if (str_contains($proofField, ',')) {
            foreach (explode(',', $proofField) as $alternative) {
                $alternative = trim($alternative);
                foreach ($candidates as $pointer) {
                    if ($pointer->field === $alternative) {
                        return $pointer;
                    }
                }
            }
        }

        return null;
    }

    private function missingWording(Range $range): string
    {
        return 0 === $range->missingSample ? 'champ absent' : sprintf('manque %d PR', $range->missingSample);
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

    private function frenchNumber(int $count): string
    {
        return match ($count) {
            2 => 'deux',
            3 => 'trois',
            4 => 'quatre',
            default => (string) $count,
        };
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
     * `Ce qui a mené là`'s block title and the other three named blocks (§ 9: "les titres de
     * bloc passent par `section()`" — soulignés d'une ligne de tirets, accepté tel quel). A
     * fresh `SymfonyStyle` on its own `BufferedOutput`, undecorated: no color escape code ever
     * reaches the buffer, and the underline's width only depends on the title string itself
     * (`Helper::width()`), never on `COLUMNS` (§ 9, contrainte 1).
     */
    private function sectionTitle(string $title): string
    {
        $buffer = new BufferedOutput();
        $io = new SymfonyStyle(new ArrayInput([]), $buffer);
        $io->section($title);

        return rtrim($buffer->fetch(), "\n");
    }

    /**
     * Word-wraps `$text` to `MAX_WIDTH` columns, indenting every continuation line under
     * `$prefix`. Never given a pointer to wrap: a pointer is always appended on its own,
     * separate, unwrapped line by the caller (§ 2, invariant 3).
     */
    private function wrapped(string $prefix, string $text): string
    {
        return $this->wrappedOn($prefix, ' ', $text);
    }

    /**
     * Same as `wrapped()`, but folds only on the given separator — used by the synthesis line
     * (§ 5.4: "elle se replie … sur un séparateur ` · `, jamais au milieu d'un couple
     * axe–niveau").
     *
     * @param non-empty-string $separator
     */
    private function wrappedOn(string $prefix, string $separator, string $text): string
    {
        $indent = str_repeat(' ', mb_strlen($prefix));
        $available = max(self::MAX_WIDTH - mb_strlen($prefix), 20);

        $words = explode($separator, $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = '' === $current ? $word : $current.$separator.$word;
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
