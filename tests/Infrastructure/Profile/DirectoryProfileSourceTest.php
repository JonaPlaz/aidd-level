<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Infrastructure\Profile;

use AiddLevel\Domain\Exception\ProfileNotAssessable;
use AiddLevel\Domain\Profile\ContextFiles;
use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;
use AiddLevel\Infrastructure\Profile\DirectoryProfileSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Loads the four profiles shipped in `profiles/` (docs/calibration.md) and a handful of
 * broken fixtures fabricated under a temporary directory (docs/specs/05-robustesse.md § Tests).
 */
final class DirectoryProfileSourceTest extends TestCase
{
    private const string PROFILES_DIR = __DIR__.'/../../../profiles';

    private string $sandbox;

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir().'/aidd-level-profile-source-'.bin2hex(random_bytes(8));
        mkdir($this->sandbox, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->sandbox);
    }

    #[Test]
    public function loadsThePercevalProfile(): void
    {
        $profile = new DirectoryProfileSource()->load(self::PROFILES_DIR.'/perceval');

        self::assertSame('perceval', $profile->identity->id);
        self::assertSame(2.0, $profile->gitActivity->medianFilesChanged);
        self::assertSame(4.0, $profile->gitActivity->medianCorrectionCommitsAfterOpen);
        self::assertSame(0.04, $profile->gitActivity->aiCoauthoredRatio);
        self::assertInstanceOf(ContextFiles::class, $profile->gitActivity->contextFiles);
        self::assertFalse($profile->gitActivity->contextFiles->agentsMd);
    }

    #[Test]
    public function loadsTheBohortProfile(): void
    {
        $profile = new DirectoryProfileSource()->load(self::PROFILES_DIR.'/bohort');

        self::assertSame(251.5, $profile->gitActivity->medianLinesChanged);
    }

    #[Test]
    public function loadsTheArthurProfileWithoutDeclarativeOrPullRequests(): void
    {
        $profile = new DirectoryProfileSource()->load(self::PROFILES_DIR.'/arthur');

        self::assertStringContainsString('déclaratif', (string) $profile->identity->note);
        self::assertNull($profile->declarative);
        self::assertNull($profile->pullRequests);
        self::assertNotContains('declaratif.md', $profile->presentPieces);
        self::assertNotContains('pull-requests.json', $profile->presentPieces);
    }

    #[Test]
    public function loadsTheLeodaganProfileRepoContext(): void
    {
        $profile = new DirectoryProfileSource()->load(self::PROFILES_DIR.'/leodagan');

        self::assertInstanceOf(RepoContext::class, $profile->repoContext);
        $paths = array_map(static fn (RepoFile $file): string => $file->path, $profile->repoContext->files);

        self::assertContains('.claude/hooks/check-assertions.js', $paths);
        self::assertContains('aidd_docs/memory/architecture.md', $paths);
    }

    #[Test]
    public function everyShippedProfileLoadsWithoutException(): void
    {
        $source = new DirectoryProfileSource();

        foreach (['perceval', 'bohort', 'leodagan', 'arthur'] as $name) {
            $profile = $source->load(self::PROFILES_DIR.'/'.$name);
            self::assertNotSame('', $profile->identity->id);
            self::assertGreaterThanOrEqual(1, $profile->gitActivity->pullRequestsTotal);
        }
    }

    #[Test]
    public function refusesANonExistentPath(): void
    {
        $missing = $this->sandbox.'/does-not-exist';

        try {
            new DirectoryProfileSource()->load($missing);
            self::fail('Expected ProfileNotAssessable.');
        } catch (ProfileNotAssessable $exception) {
            self::assertSame($missing, $exception->path);
            self::assertNull($exception->partialIdentity);
        }
    }

    #[Test]
    public function refusesAnEmptyDirectory(): void
    {
        try {
            new DirectoryProfileSource()->load($this->sandbox);
            self::fail('Expected ProfileNotAssessable.');
        } catch (ProfileNotAssessable $exception) {
            self::assertNull($exception->partialIdentity);
            self::assertStringContainsString('profile.json', $exception->missingPrerequisite);
        }
    }

    #[Test]
    public function refusesACorruptedProfileJson(): void
    {
        file_put_contents($this->sandbox.'/profile.json', '{not json');

        try {
            new DirectoryProfileSource()->load($this->sandbox);
            self::fail('Expected ProfileNotAssessable.');
        } catch (ProfileNotAssessable $exception) {
            self::assertNull($exception->partialIdentity);
            self::assertStringContainsString('profile.json', $exception->missingPrerequisite);
        }
    }

    #[Test]
    public function refusesAMissingGitActivityJson(): void
    {
        file_put_contents($this->sandbox.'/profile.json', json_encode(['profile_id' => 'ghost']));

        try {
            new DirectoryProfileSource()->load($this->sandbox);
            self::fail('Expected ProfileNotAssessable.');
        } catch (ProfileNotAssessable $exception) {
            self::assertNotNull($exception->partialIdentity);
            self::assertSame('ghost', $exception->partialIdentity->id);
            self::assertStringContainsString('git-activity.json', $exception->missingPrerequisite);
        }
    }

    #[Test]
    public function refusesAZeroPullRequestTotal(): void
    {
        file_put_contents($this->sandbox.'/profile.json', json_encode(['profile_id' => 'ghost']));
        file_put_contents($this->sandbox.'/git-activity.json', json_encode([
            'pull_requests' => ['total' => 0],
        ]));

        try {
            new DirectoryProfileSource()->load($this->sandbox);
            self::fail('Expected ProfileNotAssessable.');
        } catch (ProfileNotAssessable $exception) {
            self::assertNotNull($exception->partialIdentity);
            self::assertStringContainsString('pull_requests.total = 0', $exception->missingPrerequisite);
        }
    }

    #[Test]
    public function refusesAMissingPullRequestTotalWithADifferentMessageThanZero(): void
    {
        file_put_contents($this->sandbox.'/profile.json', json_encode(['profile_id' => 'ghost']));
        file_put_contents($this->sandbox.'/git-activity.json', json_encode(['pull_requests' => []]));

        try {
            new DirectoryProfileSource()->load($this->sandbox);
            self::fail('Expected ProfileNotAssessable.');
        } catch (ProfileNotAssessable $exception) {
            self::assertNotNull($exception->partialIdentity);
            self::assertStringContainsString('pull_requests.total est absent', $exception->missingPrerequisite);
            self::assertStringNotContainsString('= 0', $exception->missingPrerequisite);
        }
    }

    #[Test]
    public function everyBrokenFixtureRaisesADistinctPrerequisite(): void
    {
        $source = new DirectoryProfileSource();

        // 2. empty directory
        $empty = $this->sandbox.'/empty';
        mkdir($empty);

        // 3. corrupted profile.json
        $corrupted = $this->sandbox.'/corrupted';
        mkdir($corrupted);
        file_put_contents($corrupted.'/profile.json', '{not json');

        // 4. missing git-activity.json
        $noGitActivity = $this->sandbox.'/no-git-activity';
        mkdir($noGitActivity);
        file_put_contents($noGitActivity.'/profile.json', json_encode(['profile_id' => 'x']));

        // 5. total = 0
        $zeroTotal = $this->sandbox.'/zero-total';
        mkdir($zeroTotal);
        file_put_contents($zeroTotal.'/profile.json', json_encode(['profile_id' => 'x']));
        file_put_contents($zeroTotal.'/git-activity.json', json_encode(['pull_requests' => ['total' => 0]]));

        $stepMissingPath = $this->gateStep($source, $this->sandbox.'/does-not-exist');
        $stepEmpty = $this->gateStep($source, $empty);
        $stepCorrupted = $this->gateStep($source, $corrupted);
        $stepNoGitActivity = $this->gateStep($source, $noGitActivity);
        $stepZeroTotal = $this->gateStep($source, $zeroTotal);

        // Each message embeds the checked path, so the step name (which gate broke), not the
        // literal string, is what has to line up between the two profile.json fixtures and
        // be distinct across the four gate steps.
        self::assertSame($stepEmpty, $stepCorrupted);
        self::assertCount(4, array_unique([$stepMissingPath, $stepEmpty, $stepCorrupted, $stepNoGitActivity, $stepZeroTotal]));
    }

    private function gateStep(DirectoryProfileSource $source, string $path): string
    {
        try {
            $source->load($path);
        } catch (ProfileNotAssessable $exception) {
            return match (true) {
                str_contains($exception->missingPrerequisite, 'pull_requests.total') => 'gate-4-pull-request-total',
                str_contains($exception->missingPrerequisite, 'git-activity.json') => 'gate-3-git-activity',
                str_contains($exception->missingPrerequisite, 'profile.json') => 'gate-2-profile-json',
                default => 'gate-1-readable-directory',
            };
        }

        self::fail('Expected ProfileNotAssessable.');
    }

    #[Test]
    public function gitActivityWithoutParallelismYieldsNullFieldsInsteadOfFailing(): void
    {
        file_put_contents($this->sandbox.'/profile.json', json_encode(['profile_id' => 'x']));
        file_put_contents($this->sandbox.'/git-activity.json', json_encode([
            'pull_requests' => ['total' => 5],
        ]));

        $profile = new DirectoryProfileSource()->load($this->sandbox);

        self::assertNull($profile->gitActivity->maxConcurrentBranches);
        self::assertNull($profile->gitActivity->medianConcurrentBranches);
        self::assertSame(5, $profile->gitActivity->pullRequestsTotal);
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
