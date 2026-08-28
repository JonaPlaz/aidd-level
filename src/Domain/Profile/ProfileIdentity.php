<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `profile.json`: who the profile belongs to, and the pieces they say are available —
 * compared against what is actually present to spot a declared/present mismatch
 * (docs/specs/05-robustesse.md § Cohérence annoncé / présent). `note` is free text
 * (e.g. explaining why `declaratif.md` is absent) cited by the renderer, never analyzed.
 */
final readonly class ProfileIdentity
{
    /**
     * @param list<string> $stack
     * @param list<string> $available
     */
    public function __construct(
        public string $id,
        public string $role,
        public array $stack,
        public array $available,
        public ?string $note = null,
    ) {
    }
}
