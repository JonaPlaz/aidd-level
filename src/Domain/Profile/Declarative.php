<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `declaratif.md`: free text, hors calcul (docs/specs/05-robustesse.md § Déclaratif — écart,
 * hors calcul). The domain never analyzes the text and never carries it: runtime output may
 * only mention that the piece is present, marked "non vérifiée" — quoting the questionnaire
 * itself is reserved for the README and docs/methode.md, never for a rendered assessment.
 * This is unrelated to `ProfileIdentity::$note`, which comes from `profile.json`, not from
 * this file.
 */
final readonly class Declarative
{
    public function __construct(
        public bool $present,
    ) {
    }
}
