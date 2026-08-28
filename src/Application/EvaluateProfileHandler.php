<?php

declare(strict_types=1);

namespace AiddLevel\Application;

use AiddLevel\Domain\Assessment;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\AxisEvaluator;
use AiddLevel\Domain\AxisVerdict;
use AiddLevel\Domain\Confidence\Confirmed;
use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Exception\ProfileNotAssessable;
use AiddLevel\Domain\Level;
use AiddLevel\Domain\LevelRule;
use AiddLevel\Domain\Note;
use AiddLevel\Domain\Pointer;
use AiddLevel\Domain\Profile\GitActivity;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\SonarMeasures;
use AiddLevel\Domain\ProfileSource;
use AiddLevel\Domain\Progression\RecommendationPolicy;

/**
 * The only use case (docs/specs/00-vue-ensemble.md § 4.2): gate (via `ProfileSource`, §
 * 05 Gate) → White filter (§ 05 Filtre White) → one `AxisEvaluator` per axis → `LevelRule` →
 * `Assessment`. Never lets an exception reach the caller: a broken gate is caught here and
 * turned into a `NotAssessable` `Assessment` (docs/specs/05-robustesse.md § Trois statuts de
 * sortie) — the same guarantee `DirectoryProfileSource` documents on its own `load()`.
 */
final readonly class EvaluateProfileHandler
{
    // The two facts the White filter cites (docs/specs/05-robustesse.md § Filtre White):
    // no commit co-authored with the AI, no context file at all. Shared by the four axes —
    // the filter is global, not per-axis, so the same two Evidence objects apply everywhere.
    private const string ACTIVITY_FILE = 'git-activity.json';
    private const string RATIO_FIELD = 'commits.ai_coauthored_ratio';
    private const string AGENTS_MD_FIELD = 'context_files.agents_md';

    private LevelRule $levelRule;

    /**
     * @param list<AxisEvaluator> $evaluators
     */
    public function __construct(
        private ProfileSource $profileSource,
        private array $evaluators,
        private RecommendationPolicy $recommendationPolicy,
    ) {
        $this->levelRule = new LevelRule();
    }

    public function handle(EvaluateProfile $request): Assessment
    {
        try {
            $profile = $this->profileSource->load($request->path);
        } catch (ProfileNotAssessable $exception) {
            return $this->notAssessable($exception);
        }

        $verdicts = $this->isWhiteFiltered($profile->gitActivity)
            ? $this->whiteVerdicts($profile->gitActivity)
            : $this->evaluate($profile);

        $result = $this->levelRule->apply($verdicts);
        $target = $result->floor->next();

        return new Assessment(
            status: $result->isConfirmed() ? AssessmentStatus::Evaluated : AssessmentStatus::LowConfidence,
            identity: $profile->identity,
            level: $result->floor,
            ceiling: $result->ceiling,
            cappingAxes: $result->cappingAxes,
            verdicts: $verdicts,
            recommendations: null !== $target
                ? $this->recommendationPolicy->recommend($verdicts, $result->cappingAxes, $target)
                : [],
            notes: $this->buildNotes($profile, $request->path),
        );
    }

    /**
     * @return list<AxisVerdict>
     */
    private function evaluate(Profile $profile): array
    {
        return array_map(
            static fn (AxisEvaluator $evaluator): AxisVerdict => $evaluator->evaluate($profile),
            $this->evaluators,
        );
    }

    /**
     * docs/specs/05-robustesse.md § Filtre White: `commits.ai_coauthored_ratio = 0` (strictly,
     * a real zero, never an absent ratio) **and** no `context_files` counter at all — every
     * counter null or zero, with `agents_md` explicitly `false`. Absent entirely
     * (`contextFiles === null`) counts as "no counter" too: there is nothing to show either
     * way. `perceval` (ratio 0.04) never enters this branch (docs/specs/05 § Filtre White).
     */
    private function isWhiteFiltered(GitActivity $gitActivity): bool
    {
        if (0.0 !== $gitActivity->aiCoauthoredRatio) {
            return false;
        }

        $contextFiles = $gitActivity->contextFiles;
        if (null === $contextFiles) {
            return true;
        }

        if (false !== $contextFiles->agentsMd) {
            return false;
        }

        foreach ([$contextFiles->rules, $contextFiles->skills, $contextFiles->hooks, $contextFiles->agents] as $counter) {
            if (null !== $counter && 0 !== $counter) {
                return false;
            }
        }

        return true;
    }

    /**
     * White on the four axes at once, without calling a single AxisEvaluator
     * (docs/specs/05-robustesse.md § Filtre White): one Evidence per fact, shared across axes
     * since the filter itself is global.
     *
     * @return list<AxisVerdict>
     */
    private function whiteVerdicts(GitActivity $gitActivity): array
    {
        $evidences = [
            new Evidence(
                "aucun commit co-écrit avec l'IA",
                new Pointer(self::ACTIVITY_FILE, self::RATIO_FIELD, self::formatNumber($gitActivity->aiCoauthoredRatio ?? 0.0)),
            ),
            new Evidence(
                'aucun fichier de contexte',
                new Pointer(self::ACTIVITY_FILE, self::AGENTS_MD_FIELD, 'false'),
            ),
        ];

        return array_map(
            static fn (Axis $axis): AxisVerdict => new AxisVerdict(
                axis: $axis,
                level: Level::White,
                confidence: new Confirmed(),
                evidences: $evidences,
            ),
            Axis::cases(),
        );
    }

    /**
     * docs/specs/05-robustesse.md § Trois statuts de sortie — « non évaluable » : the named
     * prerequisite, what was read anyway, the technical lead. Every prerequisite message
     * `DirectoryProfileSource` raises is "<what is missing> — <how to fix it>"; splitting on
     * that separator gives `missingPrerequisite` and `hint` without duplicating either string.
     */
    private function notAssessable(ProfileNotAssessable $exception): Assessment
    {
        [$missingPrerequisite, $hint] = self::splitPrerequisite($exception->missingPrerequisite);

        $identity = $exception->partialIdentity;
        $notes = [];
        if (null !== $identity) {
            $notes[] = new Note(
                sprintf('identité lisible malgré tout : %s', $identity->id),
                new Pointer('profile.json', 'profile_id', $identity->id),
            );
        }

        return new Assessment(
            status: AssessmentStatus::NotAssessable,
            identity: $identity,
            level: null,
            ceiling: null,
            cappingAxes: [],
            verdicts: [],
            recommendations: [],
            notes: $notes,
            missingPrerequisite: $missingPrerequisite,
            hint: $hint,
        );
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function splitPrerequisite(string $message): array
    {
        $separator = ' — ';
        $position = strpos($message, $separator);

        if (false === $position) {
            return [$message, null];
        }

        return [substr($message, 0, $position), substr($message, $position + mb_strlen($separator))];
    }

    /**
     * @return list<Note>
     */
    private function buildNotes(Profile $profile, string $path): array
    {
        $notes = $this->availabilityNotes($profile, $path);

        if (null !== $profile->sonarMeasures) {
            array_push($notes, ...$this->sonarNotes($profile->sonarMeasures));
        }

        if (null !== $profile->declarative && $profile->declarative->present) {
            $notes[] = new Note(
                'pièce déclarative présente, non vérifiée, hors calcul',
                new Pointer('declaratif.md', 'file', 'présent'),
            );
        }

        $identityNote = $profile->identity->note;
        if (null !== $identityNote && '' !== trim($identityNote)) {
            $notes[] = new Note($identityNote, new Pointer('profile.json', 'note', $identityNote));
        }

        return $notes;
    }

    /**
     * docs/specs/05-robustesse.md § Cohérence annoncé / présent, in both directions: a piece
     * `profile.json › available` announces but the folder does not have, and a piece the
     * folder has but `available` never announced. Never blocking, just a note.
     *
     * @return list<Note>
     */
    private function availabilityNotes(Profile $profile, string $path): array
    {
        $notes = [];

        foreach ($profile->identity->available as $piece) {
            if (!\in_array($piece, $profile->presentPieces, true)) {
                $notes[] = new Note(
                    sprintf('pièce annoncée, absente : %s', $piece),
                    new Pointer('profile.json', 'available', $piece),
                );
            }
        }

        foreach ($profile->presentPieces as $piece) {
            if (!\in_array($piece, $profile->identity->available, true)) {
                $notes[] = new Note(
                    sprintf('pièce présente, non annoncée : %s', $piece),
                    new Pointer($path, $piece, 'présent'),
                );
            }
        }

        return $notes;
    }

    /**
     * docs/specs/05-robustesse.md § Sonar — prérequis, hors calcul: values are cited, never
     * judged, hence no threshold here — one Note per metric, only when it is present.
     *
     * @return list<Note>
     */
    private function sonarNotes(SonarMeasures $sonarMeasures): array
    {
        $notes = [];

        if (null !== $sonarMeasures->duplication) {
            $notes[] = new Note(
                sprintf('prérequis qualité, cité sans jugement : duplication = %s %%', self::formatNumber($sonarMeasures->duplication)),
                new Pointer('sonar-measures.json', 'duplicated_lines_density', self::formatNumber($sonarMeasures->duplication)),
            );
        }

        if (null !== $sonarMeasures->coverage) {
            $notes[] = new Note(
                sprintf('prérequis qualité, cité sans jugement : couverture = %s %%', self::formatNumber($sonarMeasures->coverage)),
                new Pointer('sonar-measures.json', 'coverage', self::formatNumber($sonarMeasures->coverage)),
            );
        }

        return $notes;
    }

    /**
     * A whole number (e.g. 37.0) renders as "37", a fractional one (e.g. 18.4) keeps its
     * decimal — the same rendering `InterventionEvaluator::formatNumber()` uses, kept local
     * here rather than shared: two call sites do not justify an extraction.
     */
    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4f', $value), '0'), '.');
    }
}
