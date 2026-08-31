<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The legend rendered once, at the first citation of a piece in a profile's output
 * (docs/specs/06-sortie-et-progression.md § 4): what the piece *is*, never what it is worth —
 * a legend never cites a measured value, so it carries no `Pointer` of its own and the rule 4
 * grammar (§ 2) does not apply to it (§ 4, verbatim: "la légende n'est pas une affirmation sur
 * le profil").
 *
 * A piece missing from this table gets no legend at all — that is a gap in the table, fixed
 * here, never invented at render time (§ 4, cas dégradé).
 */
final class SourceGlossary
{
    /** @var array<string, string> */
    private const array LEGENDS = [
        'git-activity.json' => "l'activité git du profil, déjà agrégée : PR, commits, branches "
            .'et fichiers de contexte, sur la période du fichier',
        'repo-context/' => 'la copie des fichiers de configuration IA trouvés à la racine du dépôt',
        'profile.json' => "l'identité du profil et la liste des pièces annoncées",
        'sonar-measures.json' => 'les mesures de qualité fournies avec le profil, citées sans jugement',
    ];

    /**
     * `repo-context/` is matched by prefix — every file under it (`repo-context/.github/…`)
     * shares the same legend, the directory itself. The other three pieces are matched
     * exactly: `git-activity.json` and `sonar-measures.json` are never nested, and
     * `profile.json` must not accidentally match a longer path that merely starts with the
     * same characters (e.g. `profiles/galahad/profile.json`).
     */
    public static function legendFor(string $file): ?string
    {
        if (\array_key_exists($file, self::LEGENDS)) {
            return self::LEGENDS[$file];
        }

        if (str_starts_with($file, 'repo-context/')) {
            return self::LEGENDS['repo-context/'];
        }

        return null;
    }
}
