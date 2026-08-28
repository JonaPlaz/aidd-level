<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Infrastructure\Profile;

use AiddLevel\Infrastructure\Profile\RepoContextReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The adaptor never judges (docs/specs/05-robustesse.md § Gate is the only place that
 * blocks): an unreadable entry under `repo-context/` is skipped, the readable siblings are
 * still inventoried. Skipped when running as root (the project's own Docker image does):
 * root bypasses the permission bits this test relies on, so it cannot observe the failure
 * `RecursiveIteratorIterator::CATCH_GET_CHILD` is meant to catch.
 */
final class RepoContextReaderTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        if (\function_exists('posix_getuid') && 0 === posix_getuid()) {
            self::markTestSkipped('Running as root: permission bits cannot make a directory unreadable.');
        }

        $this->sandbox = sys_get_temp_dir().'/aidd-level-repo-context-'.bin2hex(random_bytes(8));
        mkdir($this->sandbox, recursive: true);
    }

    protected function tearDown(): void
    {
        if (!isset($this->sandbox)) {
            return;
        }

        chmod($this->sandbox.'/denied', 0o755);
        $this->removeDirectory($this->sandbox);
    }

    #[Test]
    public function anUnreadableSubdirectoryIsSkippedInsteadOfFailingTheWholeRead(): void
    {
        mkdir($this->sandbox.'/docs');
        file_put_contents($this->sandbox.'/docs/readable.md', 'ok');

        mkdir($this->sandbox.'/denied');
        file_put_contents($this->sandbox.'/denied/secret.md', 'never read');
        chmod($this->sandbox.'/denied', 0o000);

        $repoContext = RepoContextReader::read($this->sandbox);

        $paths = array_map(static fn ($file) => $file->path, $repoContext->files);
        self::assertContains('docs/readable.md', $paths);
        self::assertNotContains('denied/secret.md', $paths);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            \assert($item instanceof \SplFileInfo);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
