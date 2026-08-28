<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain;

use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Exception\MissingPointer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvidenceTest extends TestCase
{
    #[Test]
    public function buildsWithAClaimAndAVerifiablePointer(): void
    {
        $evidence = new Evidence(
            claim: 'L median files changed = 13',
            pointer: 'git-activity.json › pull_requests.median_files_changed = 13',
        );

        self::assertSame('L median files changed = 13', $evidence->claim);
        self::assertSame('git-activity.json › pull_requests.median_files_changed = 13', $evidence->pointer);
    }

    #[Test]
    public function refusesAnEmptyPointer(): void
    {
        $this->expectException(MissingPointer::class);

        new Evidence(claim: 'unsupported claim', pointer: '');
    }

    #[Test]
    public function refusesAWhitespaceOnlyPointer(): void
    {
        $this->expectException(MissingPointer::class);

        new Evidence(claim: 'unsupported claim', pointer: "   \n");
    }

    #[Test]
    public function missingPointerCarriesTheRejectedClaim(): void
    {
        try {
            new Evidence(claim: 'unsupported claim', pointer: '');
            self::fail('Expected MissingPointer to be thrown.');
        } catch (MissingPointer $exception) {
            self::assertSame('unsupported claim', $exception->claim);
        }
    }
}
