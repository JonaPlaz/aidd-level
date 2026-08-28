<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Profile;

/**
 * `git-activity.json › context_files`: normalized counters for the Harness axis, independent
 * of the tool and of where the files live (docs/specs/02-axe-harness.md § Signal —
 * « jamais la marque »). Every field is nullable: a missing field inside git-activity.json
 * is not a gate failure (docs/specs/05-robustesse.md § Gate).
 */
final readonly class ContextFiles
{
    public function __construct(
        public ?bool $agentsMd,
        public ?int $rules,
        public ?int $skills,
        public ?int $hooks,
        public ?int $agents,
    ) {
    }
}
