<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain\Axis\Harness;

use AiddLevel\Domain\Axis\Harness\LoopDetector;
use AiddLevel\Domain\Axis\Harness\LoopThresholds;
use AiddLevel\Domain\Profile\RepoContext;
use AiddLevel\Domain\Profile\RepoFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * docs/specs/02-axe-harness.md § « Boucles — détection resserrée » (chantier 14, issue #45):
 * the faux-positif (FP) and vrai-positif (TP) matrix of § 7, plus the constant bordures of
 * § 7 and the degraded cases of § 6.
 */
final class LoopDetectorTest extends TestCase
{
    // --- Faux positifs : aucune boucle détectée ---------------------------------------

    #[Test]
    public function fp1WhileAndBudget210LinesApartIsNoLoopAndNoNote(): void
    {
        $repoContext = self::singleFile('scripts/deploy.sh', self::padded(3, 'while true; do check; done', 210, 'budget=1000'));

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp1bNamedRetryFarFromBudgetIsNoLoopButGetsTheUnboundedNote(): void
    {
        $repoContext = self::singleFile('scripts/flaky.sh', self::padded(3, 'retry_deploy', 210, 'budget=1000'));

        self::assertNull(LoopDetector::detect($repoContext));
        $unbounded = LoopDetector::detectUnboundedRetry($repoContext);
        self::assertNotNull($unbounded);
        self::assertSame('scripts/flaky.sh', $unbounded->file->path);
        self::assertSame(3, $unbounded->line);
    }

    #[Test]
    public function fp2aALineCommentNamingBothPatternsIsNeverReadAsCode(): void
    {
        $repoContext = self::singleFile('scripts/ci.sh', '# retry the test, max_attempts=3');

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp2bTheBodyOfABlockCommentIsNeverReadAsCode(): void
    {
        $content = implode("\n", [
            'const before = 1;',
            '/*',
            'retry the build',
            'max_attempts: 3',
            '*/',
            'const after = 2;',
        ]);
        $repoContext = self::singleFile('tools/runner.js', $content);

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp2bVariantClosingTheBlockBeforeThePatternsStillDetectsThem(): void
    {
        $content = implode("\n", [
            '/*',
            'a comment',
            '*/',
            'until make test; do :; done',
            'max_attempts: 3',
        ]);
        $repoContext = self::singleFile('tools/runner.js', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(4, $match->retryLine);
        self::assertSame(5, $match->boundLine);
    }

    #[Test]
    public function fp3ABoundDeclarationAloneIsNeverReadAsARetryConstruct(): void
    {
        $repoContext = self::singleFile('scripts/config.js', 'export const MAX_ATTEMPTS = 3;');

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp4TwoLinesApartInANonEligibleSurfaceIsNeitherALoopNorANote(): void
    {
        $content = implode("\n", [
            'export function bill() {',
            '  let count = 0;',
            '  while (count < budget) {',
            '    count++;',
            '  }',
            '}',
        ]);
        $repoContext = self::singleFile('src/Billing.ts', $content);

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp5ARealBrainstormFileUnderDocsIsExcludedEvenWithBothTokens(): void
    {
        $repoContext = self::singleFile(
            'docs/brainstorm/2026-06-auto-retry.md',
            "retry until it passes\nmax_attempts=3\nNot decided.",
        );

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp6ACountedLoopOfFiftyIsABatchNotABoundedRetry(): void
    {
        $repoContext = self::singleFile(
            'scripts/seed.sh',
            'for i in $(seq 1 50); do seed_user $i; done',
        );

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function fp7UnrestrictedSeqAndBraceFormsNeverCountAsABoundedLength(): void
    {
        $content = implode("\n", [
            'for i in $(seq 100 -1 1); do rollback $i; done',
            'for j in {-100..20}; do rollback $j; done',
        ]);
        $repoContext = self::singleFile('scripts/rollback.sh', $content);

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    // --- Vrais positifs : boucle détectée, preuve pointée ------------------------------

    #[Test]
    public function tp1MakefileUntilAndNumericShellTestTwoLinesApart(): void
    {
        $content = implode("\n", [
            'retry:',
            '@n=0; \\',
            'until make test; do \\',
            'n=$((n+1)); \\',
            '[ $n -ge 3 ] && exit 1; \\',
            'done',
        ]);
        $repoContext = self::singleFile('Makefile', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(3, $match->retryLine);
        self::assertSame(5, $match->boundLine);
    }

    #[Test]
    public function tp2WorkflowRetryAndMaxAttemptsThreeLinesApart(): void
    {
        $content = implode("\n", [
            'name: CI',
            'on: [push]',
            'jobs:',
            '  build:',
            '    runs-on: ubuntu-latest',
            '    steps:',
            '      - name: Install',
            '        run: make build',
            '      - uses: nick-fields/retry@v3',
            '        with:',
            '          timeout_minutes: 5',
            '          max_attempts: 3',
            '          command: make test',
        ]);
        $repoContext = self::singleFile('.github/workflows/ci.yml', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(9, $match->retryLine);
        self::assertSame(12, $match->boundLine);
    }

    #[Test]
    public function tp3CountedLoopOnASingleLine(): void
    {
        $repoContext = self::singleFile(
            'scripts/retry.sh',
            'for i in $(seq 1 3); do make test && break; done',
        );

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(1, $match->retryLine);
        self::assertSame(1, $match->boundLine);
        self::assertSame('relance et borne L1 « $(seq 1 3) »', $match->describe());
    }

    #[Test]
    public function tp4TheBoundDeclaredBeforeTheRetry(): void
    {
        $content = implode("\n", [
            'MAX_ATTEMPTS=3',
            '',
            '',
            '',
            'until make test; do',
            '  echo retrying',
            'done',
        ]);
        $repoContext = self::singleFile('scripts/loop.sh', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(1, $match->boundLine);
        self::assertSame(5, $match->retryLine);
    }

    #[Test]
    public function tp5GitlabCiRetryAndMaxOneLineApart(): void
    {
        $content = implode("\n", [
            'test:',
            '  script: make test',
            '  retry:',
            '    max: 2',
        ]);
        $repoContext = self::singleFile('.gitlab-ci.yml', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(3, $match->retryLine);
        self::assertSame(4, $match->boundLine);
    }

    #[Test]
    public function tp6ASurfaceUnderHooksIsEligibleWhateverTheTool(): void
    {
        $repoContext = self::singleFile(
            '.claude/hooks/verify.sh',
            'until make test; do n=$((n+1)); [ $n -ge 3 ] && break; done',
        );

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame('.claude/hooks/verify.sh', $match->file->path);
    }

    #[Test]
    public function tp7AGithubCompositeActionIsEligible(): void
    {
        $content = implode("\n", [
            'runs:',
            '  using: composite',
            '  steps:',
            '    - uses: nick-fields/retry@v3',
            '      with:',
            '        max_attempts: 3',
        ]);
        $repoContext = self::singleFile('.github/actions/test-with-retry/action.yml', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(4, $match->retryLine);
        self::assertSame(6, $match->boundLine);
    }

    // --- Bordures des constantes -------------------------------------------------------

    #[Test]
    public function proximityOfExactlyTenLinesIsDetected(): void
    {
        $lines = array_fill(0, 11, '');
        $lines[0] = 'until make test; do :; done';
        $lines[10] = 'budget=3';
        $repoContext = self::singleFile('scripts/edge.sh', implode("\n", $lines));

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(1, $match->retryLine);
        self::assertSame(11, $match->boundLine);
        self::assertSame(LoopThresholds::PROXIMITY_LINES, $match->boundLine - $match->retryLine);
    }

    #[Test]
    public function proximityOfElevenLinesIsNotDetected(): void
    {
        $lines = array_fill(0, 12, '');
        $lines[0] = 'until make test; do :; done';
        $lines[11] = 'budget=3';
        $repoContext = self::singleFile('scripts/edge.sh', implode("\n", $lines));

        self::assertNull(LoopDetector::detect($repoContext));
    }

    #[Test]
    public function countedLoopOfExactlyTwentyIsDetected(): void
    {
        $repoContext = self::singleFile('scripts/edge.sh', 'for i in $(seq 1 20); do run; done');

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(LoopThresholds::COUNTED_MAX, 20);
    }

    #[Test]
    public function countedLoopOfTwentyOneIsNotDetected(): void
    {
        $repoContext = self::singleFile('scripts/edge.sh', 'for i in $(seq 1 21); do run; done');

        self::assertNull(LoopDetector::detect($repoContext));
    }

    // --- Cas dégradés (§ 6) -------------------------------------------------------------

    #[Test]
    public function anEmptyFileHasNoMatchAndNoException(): void
    {
        $repoContext = self::singleFile('scripts/empty.sh', '');

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function binaryContentHasNoMatchAndNoException(): void
    {
        // Arbitrary bytes, none of them a retry or bound token: exercises the same code
        // path a truly unreadable file would (docs/specs/02-axe-harness.md § Cas dégradés —
        // a failing `preg_match`/`preg_split` must read as "no match", never an exception).
        $binary = "\xFF\xFE\x00\x01\x02\x03\xFE\xFF";
        $repoContext = self::singleFile('scripts/blob.sh', $binary);

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function windowsLineEndingsStillCountRealLineNumbers(): void
    {
        $content = "until make test; do :; done\r\nbudget=3\r\n";
        $repoContext = self::singleFile('scripts/crlf.sh', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(1, $match->retryLine);
        self::assertSame(2, $match->boundLine);
    }

    #[Test]
    public function aBoundAloneNeverTriggersTheUnboundedRetryNote(): void
    {
        $repoContext = self::singleFile('scripts/config.sh', 'MAX_ATTEMPTS=3');

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function commentOnlyPatternsNeverTriggerTheUnboundedRetryNote(): void
    {
        $content = "# retry until it passes\n# max_attempts=3";
        $repoContext = self::singleFile('scripts/config.sh', $content);

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function aCountedLoopOutOfSyntaxNeverTriggersTheUnboundedRetryNote(): void
    {
        $repoContext = self::singleFile('scripts/seed.sh', 'for i in $(seq 1 50); do seed_user $i; done');

        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function anUnboundedWhileNeverTriggersTheNoteButAnUnboundedRetryDoes(): void
    {
        $whileContext = self::singleFile('scripts/loop.sh', self::padded(1, 'while true; do check; done', 50, 'budget=1'));
        self::assertNull(LoopDetector::detectUnboundedRetry($whileContext));

        $retryContext = self::singleFile('scripts/loop.sh', self::padded(1, 'retry now', 50, 'budget=1'));
        $unbounded = LoopDetector::detectUnboundedRetry($retryContext);
        self::assertNotNull($unbounded);
        self::assertSame(1, $unbounded->line);
    }

    #[Test]
    public function multiplePairsInTheSameFileYieldOnlyOneEvidenceTheNearestWins(): void
    {
        // A Makefile target literally named "retry" sits 4 lines from a bound that is
        // actually 2 lines from the `until` that restarts something (TP1's exact shape):
        // the nearer pair wins, never the farther one merely because it scans first.
        $content = implode("\n", [
            'retry:',
            '@n=0; \\',
            'until make test; do \\',
            'n=$((n+1)); \\',
            '[ $n -ge 3 ] && exit 1; \\',
            'done',
        ]);
        $repoContext = self::singleFile('Makefile', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match);
        self::assertSame(3, $match->retryLine, 'the nearer `until` must win over the farther `retry:` target name');
        self::assertSame(5, $match->boundLine);
    }

    #[Test]
    public function twoUnboundedRetriesInTwoFilesYieldOnlyTheFirstFileInReadingOrder(): void
    {
        $repoContext = new RepoContext(files: [
            new RepoFile('scripts/first.sh', self::padded(1, 'retry now', 50, 'budget=1')),
            new RepoFile('scripts/second.sh', self::padded(1, 'retry now', 50, 'budget=1')),
        ]);

        $unbounded = LoopDetector::detectUnboundedRetry($repoContext);
        self::assertNotNull($unbounded);
        self::assertSame('scripts/first.sh', $unbounded->file->path);
    }

    // --- Passe de correction (revue Codex, PR #50) -------------------------------------

    #[Test]
    public function aBlockCommentOpenedAndClosedOnTheSameLineOnlyDropsThatLine(): void
    {
        $content = implode("\n", [
            '/* generated */',
            'until make test; do :; done',
            'max_attempts: 3',
        ]);
        $repoContext = self::singleFile('scripts/generated.sh', $content);

        $match = LoopDetector::detect($repoContext);
        self::assertNotNull($match, 'a same-line block comment must not leave the block state open for the rest of the file');
        self::assertSame(2, $match->retryLine);
        self::assertSame(3, $match->boundLine);
    }

    #[Test]
    public function aWordThatMerelyStartsWithARetryTokenIsNeverReadAsARestart(): void
    {
        $repoContext = self::singleFile('scripts/config.js', self::padded(1, 'const retryable = true;', 3, 'const budget = 10;'));

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function aWordThatMerelyStartsWithUntilIsNeverReadAsARestart(): void
    {
        $repoContext = self::singleFile('scripts/config.js', self::padded(1, 'const untilted = true;', 3, 'const budget = 10;'));

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    #[Test]
    public function invalidUtf8ContentHasNoMatchEvenWithBothTokensOnOneLine(): void
    {
        $repoContext = self::singleFile('scripts/blob.sh', "\xFF retry\nmax_attempts=3");

        self::assertNull(LoopDetector::detect($repoContext));
        self::assertNull(LoopDetector::detectUnboundedRetry($repoContext));
    }

    private static function singleFile(string $path, string $content): RepoContext
    {
        return new RepoContext(files: [new RepoFile($path, $content)]);
    }

    /**
     * Builds a file with `$firstContent` at line `$firstLine` and `$secondContent` at line
     * `$secondLine`, every other line blank — the shape of the issue's exact complaint (two
     * tokens far apart in an otherwise long file).
     */
    private static function padded(int $firstLine, string $firstContent, int $secondLine, string $secondContent): string
    {
        $lines = array_fill(0, $secondLine, '');
        $lines[$firstLine - 1] = $firstContent;
        $lines[$secondLine - 1] = $secondContent;

        return implode("\n", $lines);
    }
}
