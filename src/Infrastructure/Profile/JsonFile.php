<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Profile;

/**
 * Decodes a JSON file into an associative array. Never throws: a missing file, an unreadable
 * file or invalid JSON all resolve to `null`, so a gate step (docs/specs/05-robustesse.md
 * § Gate) can turn that into a named prerequisite failure, and a reader for an optional piece
 * can turn it into "the piece is absent".
 */
final class JsonFile
{
    /**
     * @return array<mixed>|null
     */
    public static function decode(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            return null;
        }

        try {
            $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
