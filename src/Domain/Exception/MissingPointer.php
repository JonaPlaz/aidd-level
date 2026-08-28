<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Exception;

/**
 * Raised when a Pointer is built with a blank file, field or value: a claim without a
 * verifiable `file › field = value` location is a defect of the tool, not a valid piece of
 * evidence (docs/specs/06-sortie-et-progression.md § Cinq règles, rule 4).
 *
 * Named `pointerFile`/`pointerField`/`pointerValue` (not `file`) to avoid shadowing the
 * non-readonly `Exception::$file` property PHP already declares.
 */
final class MissingPointer extends \InvalidArgumentException implements DomainException
{
    public function __construct(
        public readonly string $pointerFile,
        public readonly string $pointerField,
        public readonly string $pointerValue,
    ) {
        parent::__construct(sprintf(
            'Pointer requires a non-empty file, field and value, got file="%s", field="%s", value="%s".',
            $pointerFile,
            $pointerField,
            $pointerValue,
        ));
    }
}
