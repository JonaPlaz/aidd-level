<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Harness;

use AiddLevel\Domain\Profile\RepoFile;

/**
 * A bounded retry loop found under `repo-context/`: the file, the two lines and the two
 * tokens that made the pair, so the proof cites exactly what a reader would re-verify by
 * opening the file (docs/specs/02-axe-harness.md § 5 « Preuves rendues »).
 */
final readonly class LoopMatch
{
    public function __construct(
        public RepoFile $file,
        public int $retryLine,
        public string $retryToken,
        public int $boundLine,
        public string $boundToken,
    ) {
    }

    /**
     * The pointer value rendered in the `Evidence` — either the counted-loop form, where a
     * single token carries both roles on the same line (`relance et borne L4 « $(seq 1 3) »`),
     * or the general two-token form (`relance L9 « retry » + borne L12 « max_attempts »`).
     */
    public function describe(): string
    {
        if ($this->retryLine === $this->boundLine && $this->retryToken === $this->boundToken) {
            return sprintf('relance et borne L%d « %s »', $this->retryLine, $this->retryToken);
        }

        return sprintf(
            'relance L%d « %s » + borne L%d « %s »',
            $this->retryLine,
            $this->retryToken,
            $this->boundLine,
            $this->boundToken,
        );
    }
}
