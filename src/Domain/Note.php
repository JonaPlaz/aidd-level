<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * A free-form remark — corroboration, a peak observed but not retained, a declared/present
 * mismatch, a quality-prerequisite figure — that still cites a verifiable pointer, the same
 * invariant `Evidence` enforces (docs/specs/06-sortie-et-progression.md § Cinq règles, rule 4).
 * A note is never part of the calculation; only its wording differs from an `Evidence`.
 */
final readonly class Note
{
    public function __construct(
        public string $text,
        public Pointer $pointer,
    ) {
    }
}
