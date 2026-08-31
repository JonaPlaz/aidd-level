<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Infrastructure\Render;

use AiddLevel\Domain\Assessment;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Confidence\Range;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Profile\ProfileIdentity;
use AiddLevel\Domain\Progression\RecommendationPolicy;
use AiddLevel\Infrastructure\Render\TextRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * docs/specs/06-sortie-et-progression.md § 12 — the numbered list below matches the spec's own
 * numbering as closely as a unit test on hand-built `Assessment`s can.
 */
final class TextRendererTest extends TestCase
{
    private const string EXPECTED_DIR = __DIR__.'/../../expected';

    #[Test]
    public function rendersAnEvaluatedAssessmentLikeTheExpectedFixture(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertSame(
            file_get_contents(self::EXPECTED_DIR.'/evaluated.txt'),
            $rendered,
        );
    }

    #[Test]
    public function twoBlockingAxesAreBothNamedNeverAveraged(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertStringContainsString(
            "Niveau suivant : 🥈 Silver — il faut que Harness et Intervention y montent tous les "
            .'deux',
            $rendered,
        );
        // Table cells (docs/specs/06 § 5.4): axis and level live in separate padded columns,
        // so each blocking axis is asserted as its own table row.
        self::assertMatchesRegularExpression('/\| Harness\s+\| 🥉 Copper \(bloque\)/', $rendered);
        self::assertMatchesRegularExpression('/\| Intervention\s+\| 🥉 Copper \(bloque\)/', $rendered);
    }

    /**
     * docs/specs/06 § 12, test 2: every `Evidence` of the blocking axis is rendered, not just
     * the first one — `evaluatedAssessment()`'s Harness verdict carries three.
     */
    #[Test]
    public function everyEvidenceOfTheBlockingAxisIsRendered(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $section = $this->blockContaining($rendered, 'Ce qui a mené là');

        self::assertStringContainsString('behavior sans boucles', $section);
        self::assertStringContainsString('règles et agents versionnés', $section);
        self::assertStringContainsString('boucles non observées', $section);
        self::assertStringContainsString('aux étapes clés', $section);

        // Harness (3 Evidence) and Intervention (1 Evidence) both block: 4 pointer lines.
        $pointerLines = array_values(array_filter(
            explode("\n", $section),
            static fn (string $line): bool => str_contains($line, ' › '),
        ));
        self::assertCount(4, $pointerLines);
    }

    #[Test]
    public function everyEvidenceLineCarriesAVerifiablePointer(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertStringContainsString(
            'git-activity.json › context_files.agents_md = true',
            $rendered,
        );
        self::assertStringContainsString(
            'git-activity.json › context_files.rules_count+skills_count+hooks_count+agents_count = 6',
            $rendered,
        );
        self::assertStringContainsString(
            'repo-context/ › bounded retry = aucune relance bornée trouvée',
            $rendered,
        );
        self::assertStringContainsString(
            'git-activity.json › pull_requests.median_correction_commits_after_open = 1',
            $rendered,
        );
    }

    #[Test]
    public function everyAcquiredAxisCarriesItsOwnPointer(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $section = $this->blockContaining($rendered, 'Déjà acquis pour');

        self::assertStringContainsString('Taille — 🥇 Gold', $section);
        self::assertStringContainsString('git-activity.json › pull_requests.median_files_changed = 29', $section);
        self::assertStringContainsString('En parallèle — 🥇 Gold', $section);
        self::assertStringContainsString('git-activity.json › parallelism.median_concurrent_branches = 4', $section);
    }

    #[Test]
    public function recommendationsAreOrderedByActionability(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $harnessPosition = strpos($rendered, '1. Harness');
        $interventionPosition = strpos($rendered, '2. Intervention');

        self::assertNotFalse($harnessPosition);
        self::assertNotFalse($interventionPosition);
        self::assertLessThan($interventionPosition, $harnessPosition);
    }

    #[Test]
    public function theFirstRecommendationIsTheNextQuest(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertStringContainsString('1. Harness (à faire en premier)', $rendered);
        self::assertStringNotContainsString('2. Intervention (à faire en premier)', $rendered);
        self::assertStringContainsString('Ce qui le prouvera : repo-context/ › bounded retry', $rendered);
        // docs/specs/06 § 5.5: "Aujourd'hui" is the state the proof field itself is coming
        // from — the pointer that observed `repo-context/ › bounded retry`, never an
        // unrelated first Evidence (Codex review of PR #72, remark 3).
        self::assertStringContainsString(
            'Aujourd\'hui : repo-context/ › bounded retry = aucune relance bornée trouvée',
            $rendered,
        );
    }

    /**
     * docs/specs/06 § 5.5, § 12 test 3: when nothing observed yet matches
     * `Recommendation::$proofField`, the quest says so explicitly instead of omitting the
     * line or falling back to an unrelated pointer (Codex review of PR #72, remark 3).
     */
    #[Test]
    public function theNextQuestSaysSoExplicitlyWhenNoPointerMatchesTheProofField(): void
    {
        $rendered = new TextRenderer()->render($this->lowConfidenceAssessment());

        self::assertStringContainsString(
            'Ce qui le prouvera : context_files.rules_count, skills_count, hooks_count, agents_count',
            $rendered,
        );
        self::assertStringContainsString(
            "Aujourd'hui : aucune preuve pointée pour ce champ pour l'instant.",
            $rendered,
        );
    }

    #[Test]
    public function noPointerLineIsEverSplitByWrapping(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        foreach (explode("\n", $rendered) as $line) {
            if (str_contains($line, '›')) {
                self::assertStringContainsString(' › ', $line, sprintf('Pointer split by a wrap: "%s"', $line));
            }
        }
    }

    #[Test]
    public function rendersALowConfidenceAssessmentLikeTheExpectedFixture(): void
    {
        $rendered = new TextRenderer()->render($this->lowConfidenceAssessment());

        self::assertSame(
            file_get_contents(self::EXPECTED_DIR.'/low-confidence.txt'),
            $rendered,
        );
    }

    #[Test]
    public function lowConfidenceIsLabelledExplicitlyWithTheCanonicalStatus(): void
    {
        $rendered = new TextRenderer()->render($this->lowConfidenceAssessment());

        self::assertStringContainsString('évalué, confiance basse', $rendered);
        self::assertStringContainsString('entre 🔹 Blue et 🥉 Copper', $rendered);
        self::assertStringContainsString('4 PR de plus', $rendered);
    }

    /**
     * docs/specs/06 § 12, test 1 (P1, Codex review of PR #72, remark 1): `floor === ceiling`
     * must never stand in for `Assessment::$status` — a `Range` masked by a lower `Confirmed`
     * bottleneck (Intervention here, floor Silver, capped from view by Harness at Copper) is
     * still `évalué, confiance basse`, never `évalué`.
     */
    #[Test]
    public function aRangeMaskedByALowerConfirmedBottleneckStaysLowConfidence(): void
    {
        $rendered = new TextRenderer()->render($this->maskedRangeAssessment());

        self::assertStringContainsString('Fiabilité : évalué, confiance basse', $rendered);
        self::assertStringNotContainsString('Fiabilité : évalué —', $rendered);
        self::assertStringContainsString('Intervention (manque 3 PR)', $rendered);
    }

    /**
     * docs/specs/06 § 12, test 5: an axis in a `Range` that does not cap the floor still
     * shows its `(fourchette)` mention, its `fourchette : entre … et …` line and its
     * `pour trancher : …` line under `Déjà acquis`; the `Fiabilité` line names it too.
     */
    #[Test]
    public function everyRangedAxisIsNamedEvenWhenItDoesNotCapTheFloor(): void
    {
        $rendered = new TextRenderer()->render($this->lowConfidenceAssessment());

        self::assertStringContainsString('En parallèle (manque 2 PR)', $rendered);

        $acquired = $this->blockContaining($rendered, 'Déjà acquis pour');
        self::assertStringContainsString('En parallèle — 🟢 Green (fourchette)', $acquired);
        self::assertStringContainsString('fourchette : entre 🟢 Green et 🥇 Gold', $acquired);
        self::assertStringContainsString('pour trancher : 2 PR de plus', $acquired);
    }

    #[Test]
    public function aMissingFieldRangeIsNeverReportedAsAShortSample(): void
    {
        // docs/specs/05-robustesse.md § Signal absent: `missingSample = 0` is a missing field
        // (here, Harness's `commits.ai_coauthored_ratio`), never a short pull-request sample —
        // "manque N PR" must not appear, and the recommendation asks to supply the field.
        $rendered = new TextRenderer()->render($this->missingRatioAssessment());

        self::assertStringContainsString('fourchette : entre ❖ White et 🔺 Red', $rendered);
        self::assertStringNotContainsString('manque', $rendered);
        self::assertStringContainsString('fournir le champ commits.ai_coauthored_ratio', $rendered);
    }

    #[Test]
    public function rendersANotAssessableAssessmentLikeTheExpectedFixture(): void
    {
        $rendered = new TextRenderer()->render($this->notAssessableAssessment());

        self::assertSame(
            file_get_contents(self::EXPECTED_DIR.'/not-assessable.txt'),
            $rendered,
        );
    }

    /**
     * docs/specs/06 § 6.2: the canonical `non évaluable` status, the identity read anyway, and
     * the three things a degraded gate says (§ 6.1's shape): what is missing, what it blocks,
     * how to unblock it.
     */
    #[Test]
    public function notAssessableNamesTheMissingPrerequisiteAndWhatWasReadAnyway(): void
    {
        $rendered = new TextRenderer()->render($this->notAssessableAssessment());

        self::assertStringStartsWith('⛔ Non évaluable', $rendered);
        self::assertStringContainsString('identité : galahad (stagiaire)', $rendered);
        self::assertStringContainsString('Ce qui manque : ', $rendered);
        self::assertStringContainsString('Ce que ça empêche : ', $rendered);
        self::assertStringContainsString('Pour débloquer : ', $rendered);
    }

    #[Test]
    public function noOutputLineExceedsTheColumnWidthExceptAPointerLine(): void
    {
        $checked = 0;
        foreach ([$this->evaluatedAssessment(), $this->lowConfidenceAssessment(), $this->notAssessableAssessment()] as $assessment) {
            $rendered = new TextRenderer()->render($assessment);
            foreach (explode("\n", $rendered) as $line) {
                ++$checked;
                if (mb_strlen($line) > 100) {
                    self::assertStringContainsString(' › ', $line, sprintf('Line too wide and not a pointer: "%s"', $line));
                }
            }
        }

        self::assertGreaterThan(0, $checked);
    }

    /**
     * docs/specs/06 § 12, test 8: the retired vocabulary never comes back, the three canonical
     * statuses stay grep-able mot pour mot.
     */
    #[Test]
    public function retiredVocabularyIsGoneButCanonicalStatusLabelsStay(): void
    {
        $evaluated = new TextRenderer()->render($this->evaluatedAssessment());
        $lowConfidence = new TextRenderer()->render($this->lowConfidenceAssessment());
        $notAssessable = new TextRenderer()->render($this->notAssessableAssessment());

        foreach ([$evaluated, $lowConfidence, $notAssessable] as $rendered) {
            self::assertStringNotContainsString('axe bloquant', $rendered);
            self::assertStringNotContainsString('ex æquo', $rendered);
            self::assertStringNotContainsString('niveau visé', $rendered);
            self::assertStringNotContainsString('Prochaine quête', $rendered);
            self::assertStringNotContainsString('Incertitude sur les autres axes', $rendered);
        }

        self::assertStringContainsString('évalué', $evaluated);
        self::assertStringContainsString('évalué, confiance basse', $lowConfidence);
        self::assertStringContainsString('Non évaluable', $notAssessable);
    }

    /**
     * docs/specs/06 § 4, § 12 test 7: the legend appears once, right before the first pointer
     * that cites the piece — a profile that reuses `git-activity.json` on every axis must not
     * repeat it.
     */
    #[Test]
    public function theLegendAppearsOnceBeforeTheFirstPointerThatCitesIt(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertSame(
            1,
            substr_count($rendered, "l'activité git du profil, déjà agrégée"),
        );
        self::assertSame(
            1,
            substr_count($rendered, 'la copie des fichiers de configuration IA trouvés à la racine du dépôt'),
        );
    }

    /**
     * docs/specs/06 § 5.6, § 12 test 9: no pointer appears twice inside Notes, and none of the
     * Notes pointers duplicates one already rendered in an earlier block.
     */
    #[Test]
    public function notesAreDeduplicated(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $notesSection = substr($rendered, (int) strpos($rendered, 'Notes'));
        $pointerLines = array_values(array_filter(
            explode("\n", $notesSection),
            static fn (string $line): bool => str_contains($line, '(').str_contains($line, '›') && str_contains($line, ' › '),
        ));

        self::assertSame(\count($pointerLines), \count(array_unique($pointerLines)));

        // "prérequis qualité" cites sonar-measures.json, never seen before this block.
        $beforeNotes = substr($rendered, 0, (int) strpos($rendered, 'Notes'));
        self::assertStringNotContainsString('duplicated_lines_density', $beforeNotes);
    }

    /**
     * docs/specs/06 § 9, contrainte 1: identical whatever `COLUMNS` says — no helper used here
     * (`section()`, `writeln()`…) reads the terminal width.
     */
    #[Test]
    public function theOutputDoesNotDependOnTheTerminalWidth(): void
    {
        $before = getenv('COLUMNS');

        putenv('COLUMNS=40');
        $narrow = new TextRenderer()->render($this->evaluatedAssessment());

        putenv('COLUMNS=200');
        $wide = new TextRenderer()->render($this->evaluatedAssessment());

        if (false === $before) {
            putenv('COLUMNS');
        } else {
            putenv('COLUMNS='.$before);
        }

        self::assertSame($narrow, $wide);
    }

    /**
     * docs/specs/06 § 9, contrainte 4, § 12 test 12: a `<` in a profile's own text (identity,
     * note, claim) is never eaten by the formatter — every user-supplied line is written raw.
     */
    #[Test]
    public function aLessThanSignInUserTextIsNeverEatenByTheFormatter(): void
    {
        $identity = new ProfileIdentity('art<hur>', 'dév <indépendant>', [], []);
        $harness = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::Blue,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('<claim with a tag-looking bit>', new Pointer('git-activity.json', 'context_files.agents_md', 'true')),
            ],
        );

        $assessment = new Assessment(
            status: AssessmentStatus::Evaluated,
            identity: $identity,
            level: Level::Blue,
            ceiling: Level::Blue,
            cappingAxes: [Axis::Harness],
            verdicts: [$harness],
            recommendations: [],
            notes: [new Note('<a note>', new Pointer('profile.json', 'note', '<value>'))],
        );

        $rendered = new TextRenderer()->render($assessment);

        self::assertStringContainsString('art<hur>', $rendered);
        self::assertStringContainsString('<claim with a tag-looking bit>', $rendered);
        self::assertStringContainsString('<a note>', $rendered);
    }

    /**
     * The block starting at the given heading, up to (but not including) the next known block
     * heading — robust to the blank line the block itself uses between its own entries, which
     * would otherwise be indistinguishable from the blank line the renderer inserts between
     * top-level blocks (both are the literal bytes `"\n\n"`).
     */
    private function blockContaining(string $rendered, string $heading): string
    {
        $start = strpos($rendered, $heading);
        self::assertNotFalse($start, sprintf('Bloc "%s" absent du rendu.', $heading));

        $nextHeadings = ['Déjà acquis', "Comment monter d'un cran", 'Notes'];
        $end = null;
        foreach ($nextHeadings as $nextHeading) {
            if ($nextHeading === $heading || str_starts_with($nextHeading, $heading)) {
                continue;
            }
            $position = strpos($rendered, "\n\n".$nextHeading, $start);
            if (false !== $position && (null === $end || $position < $end)) {
                $end = $position;
            }
        }

        return null !== $end ? substr($rendered, $start, $end - $start) : substr($rendered, $start);
    }

    private function evaluatedAssessment(): Assessment
    {
        $identity = new ProfileIdentity('arthur', 'développeur indépendant', [], []);

        $size = new AxisVerdict(
            axis: Axis::Size,
            level: Level::Gold,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('XL (median_files_changed = 29)', new Pointer('git-activity.json', 'pull_requests.median_files_changed', '29')),
            ],
        );

        $harness = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::Copper,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('behavior sans boucles', new Pointer('git-activity.json', 'context_files.agents_md', 'true')),
                new Evidence(
                    'règles et agents versionnés',
                    new Pointer('git-activity.json', 'context_files.rules_count+skills_count+hooks_count+agents_count', '6'),
                ),
                new Evidence('boucles non observées', new Pointer('repo-context/', 'bounded retry', 'aucune relance bornée trouvée')),
            ],
        );

        $intervention = new AxisVerdict(
            axis: Axis::Intervention,
            level: Level::Copper,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('aux étapes clés', new Pointer('git-activity.json', 'pull_requests.median_correction_commits_after_open', '1')),
            ],
        );

        $parallelism = new AxisVerdict(
            axis: Axis::Parallelism,
            level: Level::Gold,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('4 (médiane)', new Pointer('git-activity.json', 'parallelism.median_concurrent_branches', '4')),
            ],
        );

        $cappingAxes = [Axis::Harness, Axis::Intervention];
        $verdicts = [$size, $harness, $intervention, $parallelism];
        $recommendations = new RecommendationPolicy()->recommend($verdicts, $cappingAxes, Level::Silver);

        $notes = [
            new Note('pic observé, non retenu', new Pointer('git-activity.json', 'parallelism.max_concurrent_branches', '7')),
            new Note('prérequis qualité : duplication 2,4 %, couverture 85 %', new Pointer('sonar-measures.json', 'duplicated_lines_density', '2.4')),
            new Note("pièce annoncée absente (n'a pas répondu au questionnaire)", new Pointer('profile.json', 'note', 'declaratif.md absent')),
        ];

        return new Assessment(
            status: AssessmentStatus::Evaluated,
            identity: $identity,
            level: Level::Copper,
            ceiling: Level::Copper,
            cappingAxes: $cappingAxes,
            verdicts: $verdicts,
            recommendations: $recommendations,
            notes: $notes,
        );
    }

    private function lowConfidenceAssessment(): Assessment
    {
        $identity = new ProfileIdentity('perceval', 'développeur solo', [], []);

        $harness = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::Blue,
            confidence: new Range(Level::Blue, Level::Copper, 4),
            evidences: [
                new Evidence('context engineering sans behavior', new Pointer('git-activity.json', 'context_files.agents_md', 'true')),
            ],
        );

        $intervention = new AxisVerdict(
            axis: Axis::Intervention,
            level: Level::Green,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('après coup, sur une partie', new Pointer('git-activity.json', 'pull_requests.median_correction_commits_after_open', '2')),
            ],
        );

        $size = new AxisVerdict(
            axis: Axis::Size,
            level: Level::Red,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('S (median_files_changed = 2)', new Pointer('git-activity.json', 'pull_requests.median_files_changed', '2')),
            ],
        );

        // Not the capping axis (Harness alone holds the floor at Blue) but still a Range:
        // its own uncertainty must surface too (docs/specs/06 § 5.4).
        $parallelism = new AxisVerdict(
            axis: Axis::Parallelism,
            level: Level::Green,
            confidence: new Range(Level::Green, Level::Gold, 2),
            evidences: [
                new Evidence('1 (médiane)', new Pointer('git-activity.json', 'parallelism.median_concurrent_branches', '1')),
            ],
        );

        $cappingAxes = [Axis::Harness];
        $verdicts = [$size, $harness, $intervention, $parallelism];
        $recommendations = new RecommendationPolicy()->recommend($verdicts, $cappingAxes, Level::Green);

        $notes = [
            new Note('declaratif.md présent, non vérifié', new Pointer('profile.json', 'available', 'declaratif.md')),
        ];

        return new Assessment(
            status: AssessmentStatus::LowConfidence,
            identity: $identity,
            level: Level::Blue,
            ceiling: Level::Copper,
            cappingAxes: $cappingAxes,
            verdicts: $verdicts,
            recommendations: $recommendations,
            notes: $notes,
        );
    }

    /**
     * docs/specs/06 § 12, test 1 (P1, Codex review of PR #72, remark 1): a `Range` masked by a
     * lower `Confirmed` bottleneck can leave `Assessment::$level === $ceiling` while the
     * result is still unconfirmed (`LevelRule::apply()`'s own docblock) — the canonical
     * `évalué, confiance basse` label must come from `$status`, never from comparing the two
     * levels.
     */
    private function maskedRangeAssessment(): Assessment
    {
        $identity = new ProfileIdentity('masked', 'développeur solo', [], []);

        $harness = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::Copper,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('context engineering et behavior acquis', new Pointer('git-activity.json', 'context_files.agents_md', 'true')),
            ],
        );

        // Silver–Gold: its own floor (Silver) sits above the Harness bottleneck (Copper), so
        // it never caps the floor — but it is still a Range, so the result cannot be Confirmed.
        $intervention = new AxisVerdict(
            axis: Axis::Intervention,
            level: Level::Silver,
            confidence: new Range(Level::Silver, Level::Gold, 3),
            evidences: [
                new Evidence('jamais, une fois la tâche cadrée', new Pointer('git-activity.json', 'pull_requests.median_correction_commits_after_open', '0')),
            ],
        );

        $size = new AxisVerdict(
            axis: Axis::Size,
            level: Level::Gold,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('XL (median_files_changed = 25)', new Pointer('git-activity.json', 'pull_requests.median_files_changed', '25')),
            ],
        );

        $parallelism = new AxisVerdict(
            axis: Axis::Parallelism,
            level: Level::Gold,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('5 chantiers de front en médiane', new Pointer('git-activity.json', 'parallelism.median_concurrent_branches', '5')),
            ],
        );

        $cappingAxes = [Axis::Harness];
        $verdicts = [$size, $harness, $intervention, $parallelism];
        $recommendations = new RecommendationPolicy()->recommend($verdicts, $cappingAxes, Level::Silver);

        return new Assessment(
            status: AssessmentStatus::LowConfidence,
            identity: $identity,
            level: Level::Copper,
            ceiling: Level::Copper,
            cappingAxes: $cappingAxes,
            verdicts: $verdicts,
            recommendations: $recommendations,
            notes: [],
        );
    }

    /**
     * docs/specs/02-axe-harness.md § Ratio absent: `agents_md = false`, every counter known
     * at zero, `commits.ai_coauthored_ratio` absent — Harness renders `Range(White, Red, 0)`,
     * the missing field, not a short pull-request sample.
     */
    private function missingRatioAssessment(): Assessment
    {
        $identity = new ProfileIdentity('fixture', 'développeur solo', [], []);

        $harness = new AxisVerdict(
            axis: Axis::Harness,
            level: Level::White,
            confidence: new Range(Level::White, Level::Red, 0),
            evidences: [
                new Evidence('aucun fichier mémoire', new Pointer('git-activity.json', 'context_files.agents_md', 'false')),
                new Evidence(
                    'aucun compteur de contexte : 0 règle, 0 skill, 0 hook, 0 agent',
                    new Pointer('git-activity.json', 'context_files', '{rules:0, skills:0, hooks:0, agents:0}'),
                ),
            ],
            notes: [
                new Note('ratio absent : impossible de départager prompts de rien', new Pointer('git-activity.json', 'commits.ai_coauthored_ratio', 'absent')),
            ],
        );

        $size = new AxisVerdict(
            axis: Axis::Size,
            level: Level::Red,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('S (median_files_changed = 2)', new Pointer('git-activity.json', 'pull_requests.median_files_changed', '2')),
            ],
        );

        $intervention = new AxisVerdict(
            axis: Axis::Intervention,
            level: Level::Red,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('après coup, sur la majorité', new Pointer('git-activity.json', 'pull_requests.median_correction_commits_after_open', '4')),
            ],
        );

        $parallelism = new AxisVerdict(
            axis: Axis::Parallelism,
            level: Level::Red,
            confidence: new Confirmed(),
            evidences: [
                new Evidence('0 (médiane)', new Pointer('git-activity.json', 'parallelism.median_concurrent_branches', '0')),
            ],
        );

        $cappingAxes = [Axis::Harness];
        $verdicts = [$size, $harness, $intervention, $parallelism];
        $recommendations = new RecommendationPolicy()->recommend($verdicts, $cappingAxes, Level::Red);

        return new Assessment(
            status: AssessmentStatus::LowConfidence,
            identity: $identity,
            level: Level::White,
            ceiling: Level::Red,
            cappingAxes: $cappingAxes,
            verdicts: $verdicts,
            recommendations: $recommendations,
            notes: [],
        );
    }

    private function notAssessableAssessment(): Assessment
    {
        $identity = new ProfileIdentity('galahad', 'stagiaire', [], []);

        $notes = [
            new Note('profile.json lisible malgré tout', new Pointer('profiles/galahad/profile.json', 'id', 'galahad')),
        ];

        return new Assessment(
            status: AssessmentStatus::NotAssessable,
            identity: $identity,
            level: null,
            ceiling: null,
            cappingAxes: [],
            verdicts: [],
            recommendations: [],
            notes: $notes,
            missingPrerequisite: "git-activity.json absent ou invalide — colonne vertébrale, aucun axe n'est calculable sans lui",
            hint: 'fournir un git-activity.json valide à la racine du dossier de profil (profiles/galahad/)',
        );
    }
}
