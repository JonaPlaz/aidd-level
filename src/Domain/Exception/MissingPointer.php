<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Exception;

/**
 * Raised when an Evidence is built without a pointer: a claim without a verifiable
 * `file › field = value` location is a defect of the tool, not a valid Evidence
 * (docs/specs/06-sortie-et-progression.md § Cinq règles, rule 4).
 */
final class MissingPointer extends \InvalidArgumentException implements DomainException
{
    public function __construct(public readonly string $claim)
    {
        parent::__construct(sprintf('Evidence for claim "%s" requires a non-empty pointer.', $claim));
    }
}
