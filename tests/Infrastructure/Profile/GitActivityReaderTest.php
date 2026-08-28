<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Infrastructure\Profile;

use AiddLevel\Infrastructure\Profile\GitActivityReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A field declared an integer in the domain (docs/specs/05-robustesse.md § Gate: "un champ
 * manquant à l'intérieur de git-activity.json n'est pas un échec") must actually be a whole
 * number: truncating a non-integer float would invent a count never present in the source.
 */
final class GitActivityReaderTest extends TestCase
{
    #[Test]
    public function aWholeNumberEncodedAsAFloatIsAccepted(): void
    {
        $gitActivity = GitActivityReader::read([
            'pull_requests' => ['total' => 5, 'merged_without_human_edit_after_open' => 2.0],
        ]);

        self::assertSame(2, $gitActivity->mergedWithoutHumanEditAfterOpen);
    }

    #[Test]
    public function aFractionalFloatIsRejectedRatherThanTruncated(): void
    {
        $gitActivity = GitActivityReader::read([
            'pull_requests' => ['total' => 5, 'merged_without_human_edit_after_open' => 1.9],
        ]);

        self::assertNull($gitActivity->mergedWithoutHumanEditAfterOpen);
    }

    #[Test]
    public function anotherFractionalFloatIsAlsoRejected(): void
    {
        $gitActivity = GitActivityReader::read([
            'parallelism' => ['max_concurrent_branches' => 2.9],
        ]);

        self::assertNull($gitActivity->maxConcurrentBranches);
    }

    #[Test]
    public function aMissingParallelismBlockYieldsNullFieldsInsteadOfFailing(): void
    {
        $gitActivity = GitActivityReader::read(['pull_requests' => ['total' => 5]]);

        self::assertNull($gitActivity->maxConcurrentBranches);
        self::assertNull($gitActivity->medianConcurrentBranches);
        self::assertSame(5, $gitActivity->pullRequestsTotal);
    }
}
