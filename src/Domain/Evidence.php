<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Exception\MissingPointer;

/**
 * A claim and its verifiable pointer (`file › field = value`, docs/specs/06-sortie-et-progression.md
 * § Cinq règles, rule 4). A claim without a pointer cannot be built: it would be a defect of
 * the tool, not a valid piece of evidence.
 */
final readonly class Evidence
{
    public function __construct(
        public string $claim,
        public string $pointer,
    ) {
        if (trim($this->pointer) === '') {
            throw new MissingPointer($this->claim);
        }
    }
}
