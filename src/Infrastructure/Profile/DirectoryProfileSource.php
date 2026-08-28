<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Profile;

use AiddLevel\Domain\Exception\ProfileNotAssessable;
use AiddLevel\Domain\Profile\Declarative;
use AiddLevel\Domain\Profile\Profile;
use AiddLevel\Domain\Profile\ProfileIdentity;
use AiddLevel\Domain\ProfileSource;
use AiddLevel\Domain\Threshold\SampleFloors;

/**
 * Reads a profile folder from disk (docs/specs/00-vue-ensemble.md § 4.3), enforcing the gate
 * in order (docs/specs/05-robustesse.md § Gate). No Composer dependency: `json_decode` is
 * enough. Each gate step names its own prerequisite, so a caller can report exactly which one
 * broke without reopening the folder.
 */
final class DirectoryProfileSource implements ProfileSource
{
    // The four named prerequisites read by the jury (docs/specs/05-robustesse.md § Trois
    // statuts de sortie): missingPrerequisite is user-facing output, so it is French, and each
    // one states the fix, not just the failure. Code, identifiers and comments stay English.
    private const string PREREQUISITE_READABLE_DIRECTORY = '%s n\'est pas un dossier lisible — fournir un chemin de dossier de profil existant';
    private const string PREREQUISITE_PROFILE_JSON = 'profile.json absent ou illisible dans %s — fournir un profile.json valide';
    private const string PREREQUISITE_GIT_ACTIVITY_JSON = 'git-activity.json absent ou illisible dans %s — fournir un git-activity.json valide (colonne vertébrale de l\'évaluation)';
    private const string PREREQUISITE_PULL_REQUEST_TOTAL_MISSING = 'git-activity.json › pull_requests.total est absent dans %s — fournir ce champ';
    private const string PREREQUISITE_PULL_REQUEST_TOTAL_ZERO = 'git-activity.json › pull_requests.total = %d dans %s — aucune PR sur la période, rien à mesurer';

    /** Piece names as declared by `profile.json › available` and checked against the real inventory. */
    private const array KNOWN_PIECES = [
        'git-activity.json',
        'pull-requests.json',
        'code/',
        'sonar-measures.json',
        'repo-context/',
        'declaratif.md',
        'session.md',
    ];

    public function load(string $path): Profile
    {
        if (!is_dir($path) || !is_readable($path)) {
            throw new ProfileNotAssessable(sprintf(self::PREREQUISITE_READABLE_DIRECTORY, $path), $path);
        }

        $profileData = JsonFile::decode($path.'/profile.json');
        if (null === $profileData) {
            throw new ProfileNotAssessable(sprintf(self::PREREQUISITE_PROFILE_JSON, $path), $path);
        }

        $identity = $this->buildIdentity($profileData);

        $gitActivityData = JsonFile::decode($path.'/git-activity.json');
        if (null === $gitActivityData) {
            throw new ProfileNotAssessable(sprintf(self::PREREQUISITE_GIT_ACTIVITY_JSON, $path), $path, $identity);
        }

        $gitActivity = GitActivityReader::read($gitActivityData);

        if (null === $gitActivity->pullRequestsTotal) {
            throw new ProfileNotAssessable(sprintf(self::PREREQUISITE_PULL_REQUEST_TOTAL_MISSING, $path), $path, $identity);
        }

        if ($gitActivity->pullRequestsTotal < SampleFloors::GATE_MIN_PR) {
            throw new ProfileNotAssessable(
                sprintf(self::PREREQUISITE_PULL_REQUEST_TOTAL_ZERO, $gitActivity->pullRequestsTotal, $path),
                $path,
                $identity,
            );
        }

        $sonarData = JsonFile::decode($path.'/sonar-measures.json');
        $pullRequestsData = JsonFile::decode($path.'/pull-requests.json');

        return new Profile(
            identity: $identity,
            gitActivity: $gitActivity,
            repoContext: is_dir($path.'/repo-context') ? RepoContextReader::read($path.'/repo-context') : null,
            sonarMeasures: null !== $sonarData ? SonarMeasuresReader::read($sonarData) : null,
            declarative: is_file($path.'/declaratif.md') ? new Declarative(present: true) : null,
            pullRequests: null !== $pullRequestsData ? PullRequestsReader::read($pullRequestsData) : null,
            presentPieces: $this->inventory($path),
        );
    }

    /**
     * @param array<mixed> $data the already-decoded content of profile.json
     */
    private function buildIdentity(array $data): ProfileIdentity
    {
        $stack = is_array($data['stack'] ?? null)
            ? array_values(array_filter($data['stack'], is_string(...)))
            : [];
        $available = is_array($data['available'] ?? null)
            ? array_values(array_filter($data['available'], is_string(...)))
            : [];
        $note = $data['note'] ?? null;

        return new ProfileIdentity(
            id: is_string($data['profile_id'] ?? null) ? $data['profile_id'] : '',
            role: is_string($data['role'] ?? null) ? $data['role'] : '',
            stack: $stack,
            available: $available,
            note: is_string($note) ? $note : null,
        );
    }

    /**
     * @return list<string> the actual inventory of `docs/specs/00-vue-ensemble.md § 3` pieces,
     *                      compared to `ProfileIdentity::$available` by the handler
     *                      (docs/specs/05-robustesse.md § Cohérence annoncé / présent) —
     *                      `profile.json` itself is never listed, it is not an optional piece
     */
    private function inventory(string $path): array
    {
        $present = [];

        foreach (self::KNOWN_PIECES as $piece) {
            $isDirectoryPiece = str_ends_with($piece, '/');
            $target = $path.'/'.rtrim($piece, '/');

            $exists = $isDirectoryPiece ? is_dir($target) : is_file($target);
            if ($exists) {
                $present[] = $piece;
            }
        }

        return $present;
    }
}
