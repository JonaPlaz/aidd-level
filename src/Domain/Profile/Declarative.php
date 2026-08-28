<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `declaratif.md`: free text, hors calcul (docs/specs/05-robustesse.md § Déclaratif — écart,
 * hors calcul). The domain never analyzes the text: it only records that the piece is
 * present, and keeps the raw note to be cited — marked "non vérifiée" — by the output layer.
 */
final readonly class Declarative
{
    public function __construct(
        public bool $present,
        public ?string $note,
    ) {
    }
}
