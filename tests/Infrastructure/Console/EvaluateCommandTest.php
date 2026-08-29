<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Infrastructure\Console;

use AiddLevel\Infrastructure\Console\ApplicationFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `EvaluateCommand` (docs/specs/00-vue-ensemble.md § 6), driven through `CommandTester` on
 * the real `ApplicationFactory` wiring and the four real profile folders.
 */
final class EvaluateCommandTest extends TestCase
{
    private const string PROFILES_DIR = __DIR__.'/../../../profiles';

    #[Test]
    public function evaluatesASingleValidProfile(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute(['profile-dir' => [self::PROFILES_DIR.'/arthur']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Copper', $tester->getDisplay());
        self::assertStringContainsString('arthur', $tester->getDisplay());
    }

    #[Test]
    public function aBrokenProfileInABatchDoesNotStopTheOtherOne(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'profile-dir' => [self::PROFILES_DIR.'/arthur', self::PROFILES_DIR.'/does-not-exist'],
        ]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Copper', $display);
        self::assertStringContainsString('arthur', $display);
        self::assertStringContainsString('Non évaluable', $display);
    }

    #[Test]
    public function aBatchOfOnlyBrokenProfilesFails(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([
            'profile-dir' => [self::PROFILES_DIR.'/does-not-exist', self::PROFILES_DIR.'/also-missing'],
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(2, substr_count($tester->getDisplay(), 'Non évaluable'));
    }

    #[Test]
    public function withoutArgumentItListsTheProfilesFolder(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('arthur', $display);
        self::assertStringContainsString('bohort', $display);
        self::assertStringContainsString('leodagan', $display);
        self::assertStringContainsString('perceval', $display);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(ApplicationFactory::createCommand());
    }
}
