<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * A claim and its verifiable pointer (`file › field = value`, docs/specs/06-sortie-et-progression.md
 * § Cinq règles, rule 4). The pointer is a structured `Pointer` value object, not a free-form
 * string: it cannot be built without a real file, field and value, so an Evidence can never
 * carry an unverifiable or incomplete explanation line.
 */
final readonly class Evidence
{
    public function __construct(
        public string $claim,
        public Pointer $pointer,
    ) {
    }
}
