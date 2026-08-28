<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

use AiddLevel\Domain\Exception\MissingPointer;

/**
 * A verifiable location — `file › field = value` (docs/specs/06-sortie-et-progression.md §
 * Cinq règles, rule 4) — structurally, instead of a free-form string that could be missing a
 * field or a value. All three parts are mandatory: a pointer with a blank file, field or
 * value cannot be built.
 */
final readonly class Pointer
{
    public function __construct(
        public string $file,
        public string $field,
        public string $value,
    ) {
        if ('' === trim($this->file) || '' === trim($this->field) || '' === trim($this->value)) {
            throw new MissingPointer($this->file, $this->field, $this->value);
        }
    }

    /**
     * The rendered form every explanation line cites: `file › field = value`.
     */
    public function __toString(): string
    {
        return sprintf('%s › %s = %s', $this->file, $this->field, $this->value);
    }
}
