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
    public function anExAequoIsSaidAsSuchNeverAveraged(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertStringContainsString('Harness et Intervention (ex æquo)', $rendered);
    }

    #[Test]
    public function everyEvidenceLineCarriesAVerifiablePointer(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $section = substr(
            $rendered,
            (int) strpos($rendered, "Ce qui a mené là"),
            strpos($rendered, 'Acquis pour') - (int) strpos($rendered, "Ce qui a mené là"),
        );

        // Every 4-space-indented line under "Ce qui a mené là" is a raw Pointer::__toString(),
        // never wrapped (see TextRenderer::wrapped() docblock) — it must contain " › ".
        $evidenceLines = array_values(array_filter(
            explode("\n", $section),
            static fn (string $line): bool => str_starts_with($line, '    '),
        ));

        self::assertNotEmpty($evidenceLines);
        foreach ($evidenceLines as $line) {
            self::assertStringContainsString(' › ', $line);
        }
    }

    #[Test]
    public function everyAcquiredClaimCarriesItsOwnPointer(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $section = substr($rendered, (int) strpos($rendered, 'Acquis pour'));
        $section = substr($section, 0, (int) strpos($section, "\n\n"));

        self::assertStringContainsString('Taille : XL', $section);
        self::assertStringContainsString('git-activity.json › pull_requests.median_files_changed = 29', $section);
        self::assertStringContainsString('En parallèle : 4 (médiane)', $section);
        self::assertStringContainsString('git-activity.json › parallelism.median_concurrent_branches = 4', $section);
    }

    #[Test]
    public function recommendationsAreOrderedByActionability(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        $harnessPosition = strpos($rendered, '1. Harness :');
        $interventionPosition = strpos($rendered, '2. Intervention :');

        self::assertNotFalse($harnessPosition);
        self::assertNotFalse($interventionPosition);
        self::assertLessThan($interventionPosition, $harnessPosition);
    }

    #[Test]
    public function theNextQuestNamesTheProofFieldAndTheCurrentEvidence(): void
    {
        $rendered = new TextRenderer()->render($this->evaluatedAssessment());

        self::assertStringContainsString(
            "champ à faire bouger : repo-context/ › bounded retry",
            $rendered,
        );
        self::assertStringContainsString(
            'preuve actuelle : git-activity.json › context_files.agents_md = true',
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
    public function lowConfidenceIsLabelledExplicitly(): void
    {
        $rendered = new TextRenderer()->render($this->lowConfidenceAssessment());

        self::assertStringContainsString('évalué, confiance basse', $rendered);
        self::assertStringContainsString('Niveau : entre Blue et Copper', $rendered);
        self::assertStringContainsString('4 PR de plus', $rendered);
    }

    #[Test]
    public function everyRangedAxisIsNamedEvenWhenItDoesNotCapTheFloor(): void
    {
        $rendered = new TextRenderer()->render($this->lowConfidenceAssessment());

        // Parallelism is a Range here but Harness alone holds the floor down: the
        // uncertainty on Parallelism must still surface, not be folded into "Acquis".
        self::assertStringContainsString('Incertitude sur les autres axes', $rendered);
        self::assertStringContainsString('En parallèle : 1 (médiane)', $rendered);
        self::assertStringContainsString('fourchette : entre Green et Gold (manque 2 PR)', $rendered);

        $acquisSection = substr($rendered, (int) strpos($rendered, 'Acquis pour'));
        $acquisSection = substr($acquisSection, 0, (int) strpos($acquisSection, "\n\n"));
        self::assertStringNotContainsString('En parallèle', $acquisSection);
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

    #[Test]
    public function notAssessableNamesTheMissingPrerequisiteAndWhatWasReadAnyway(): void
    {
        $rendered = new TextRenderer()->render($this->notAssessableAssessment());

        self::assertStringStartsWith('⛔ Non évaluable', $rendered);
        self::assertStringContainsString('identité : galahad (stagiaire)', $rendered);
        self::assertStringContainsString('Piste technique', $rendered);
    }

    #[Test]
    public function noOutputLineExceedsTheColumnWidth(): void
    {
        foreach ([$this->evaluatedAssessment(), $this->lowConfidenceAssessment(), $this->notAssessableAssessment()] as $assessment) {
            $rendered = new TextRenderer()->render($assessment);
            foreach (explode("\n", $rendered) as $line) {
                self::assertLessThanOrEqual(100, mb_strlen($line), sprintf('Line too wide: "%s"', $line));
            }
        }
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
                new Evidence('boucles non observées', new Pointer('repo-context/', 'retry_pattern', 'aucune relance bornée trouvée')),
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
            verdicts: [$size, $harness, $intervention, $parallelism],
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
        // its own uncertainty must surface too (docs/specs/06 § Raccord avec les statuts).
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
            verdicts: [$size, $harness, $intervention, $parallelism],
            recommendations: $recommendations,
            notes: $notes,
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
