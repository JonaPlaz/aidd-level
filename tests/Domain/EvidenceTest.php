<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Domain;

use AiddLevel\Domain\Evidence;
use AiddLevel\Domain\Exception\MissingPointer;
use AiddLevel\Domain\Pointer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvidenceTest extends TestCase
{
    #[Test]
    public function buildsWithAClaimAndAVerifiablePointer(): void
    {
        $evidence = new Evidence(
            claim: 'L median files changed = 13',
            pointer: new Pointer(
                file: 'git-activity.json',
                field: 'pull_requests.median_files_changed',
                value: '13',
            ),
        );

        self::assertSame('L median files changed = 13', $evidence->claim);
        self::assertSame(
            'git-activity.json › pull_requests.median_files_changed = 13',
            (string) $evidence->pointer,
        );
    }

    #[Test]
    public function refusesAPointerWithABlankFile(): void
    {
        $this->expectException(MissingPointer::class);

        new Pointer(file: '', field: 'pull_requests.total', value: '71');
    }

    #[Test]
    public function refusesAPointerWithABlankField(): void
    {
        $this->expectException(MissingPointer::class);

        new Pointer(file: 'git-activity.json', field: '', value: '71');
    }

    #[Test]
    public function refusesAPointerWithABlankValue(): void
    {
        $this->expectException(MissingPointer::class);

        new Pointer(file: 'git-activity.json', field: 'pull_requests.total', value: '');
    }

    #[Test]
    public function refusesAPointerWithAWhitespaceOnlyValue(): void
    {
        $this->expectException(MissingPointer::class);

        new Pointer(file: 'git-activity.json', field: 'pull_requests.total', value: "   \n");
    }

    #[Test]
    public function missingPointerCarriesEveryRejectedPart(): void
    {
        try {
            new Pointer(file: 'git-activity.json', field: 'pull_requests.total', value: '');
            self::fail('Expected MissingPointer to be thrown.');
        } catch (MissingPointer $exception) {
            self::assertSame('git-activity.json', $exception->pointerFile);
            self::assertSame('pull_requests.total', $exception->pointerField);
            self::assertSame('', $exception->pointerValue);
        }
    }

    #[Test]
    public function rendersAsFileFieldEqualsValue(): void
    {
        $pointer = new Pointer(
            file: 'git-activity.json',
            field: 'parallelism.median_concurrent_branches',
            value: '1',
        );

        self::assertSame(
            'git-activity.json › parallelism.median_concurrent_branches = 1',
            (string) $pointer,
        );
    }
}
