<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Profile;

use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;

/**
 * Reads `repo-context/` recursively, hidden files included (`.claude/…` is a legitimate proof
 * of harness, not something to filter out — docs/specs/00-vue-ensemble.md § 3: « détecter
 * `.claude/` serait une faute »). Paths are relative to `repo-context/` itself; content is
 * read as-is, the pieces here are small (docs/specs/05-robustesse.md).
 */
final class RepoContextReader
{
    public static function read(string $repoContextDir): RepoContext
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($repoContextDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $paths = [];
        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof \SplFileInfo);

            if (!$fileInfo->isFile()) {
                continue;
            }

            $paths[] = $fileInfo->getPathname();
        }

        sort($paths);

        foreach ($paths as $absolutePath) {
            $relative = ltrim(substr($absolutePath, strlen($repoContextDir)), \DIRECTORY_SEPARATOR);
            $content = file_get_contents($absolutePath);

            $files[] = new RepoFile(
                path: str_replace(\DIRECTORY_SEPARATOR, '/', $relative),
                content: false !== $content ? $content : '',
            );
        }

        return new RepoContext(files: $files);
    }
}
