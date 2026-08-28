<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Profile;

use AiddLevel\Domain\Profile\SonarMeasures;

/**
 * Reads `sonar-measures.json` into a quality-prerequisite note
 * (docs/specs/05-robustesse.md § Sonar — prérequis, hors calcul). Only two metrics matter,
 * extracted from `component.measures`, a flat list of `{metric, value}` pairs where `value`
 * is a JSON string. Any other metric present is ignored; a missing one is `null`.
 */
final class SonarMeasuresReader
{
    /**
     * @param array<mixed> $data the already-decoded content of sonar-measures.json
     */
    public static function read(array $data): SonarMeasures
    {
        $component = $data['component'] ?? null;
        $measures = is_array($component) ? ($component['measures'] ?? null) : null;
        $measures = is_array($measures) ? $measures : [];

        return new SonarMeasures(
            duplication: self::metric($measures, 'duplicated_lines_density'),
            coverage: self::metric($measures, 'coverage'),
        );
    }

    /**
     * @param array<mixed> $measures
     */
    private static function metric(array $measures, string $name): ?float
    {
        foreach ($measures as $measure) {
            if (!is_array($measure) || ($measure['metric'] ?? null) !== $name) {
                continue;
            }

            $value = $measure['value'] ?? null;

            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }
}
