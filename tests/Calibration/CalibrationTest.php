<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Calibration;

use AiddLevel\Application\EvaluateProfile;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Level;
use AiddLevel\Infrastructure\Console\ApplicationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Bout en bout: real profile folder → `ApplicationFactory`'s handler → `TextRenderer` →
 * rendered text. Fixes the four verdicts `docs/calibration.md` proves against the
 * organizers' attributed levels, so a repreneur who moves a threshold sees immediately what
 * bascule (docs/calibration.md § Reproduction). Duplicates `EvaluateProfileHandlerTest`'s
 * calibrated cases on purpose: that test proves the `Assessment`, this one proves the whole
 * wiring (`ApplicationFactory`) plus the rendered text a jury actually reads.
 */
final class CalibrationTest extends TestCase
{
    private const string PROFILES_DIR = __DIR__.'/../../profiles';

    /**
     * @param list<Axis> $expectedCappingAxes
     */
    #[Test]
    #[DataProvider('calibratedProfiles')]
    public function rendersTheCalibratedLevelAndCappingAxes(string $profile, Level $expectedLevel, array $expectedCappingAxes): void
    {
        $handler = ApplicationFactory::createHandler();
        $renderer = ApplicationFactory::createRenderer();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/'.$profile));
        $rendered = $renderer->render($assessment);

        self::assertSame($expectedLevel, $assessment->level);
        self::assertEqualsCanonicalizing($expectedCappingAxes, $assessment->cappingAxes);

        self::assertStringContainsString($expectedLevel->label(), $rendered);

        foreach ($this->pointerLines($rendered) as $line) {
            self::assertStringContainsString(' › ', $line, sprintf('Ligne sans pointeur : "%s"', $line));
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: Level, 2: list<Axis>}>
     */
    public static function calibratedProfiles(): iterable
    {
        yield 'perceval -> Red, Taille+Harness+Intervention ex æquo' => [
            'perceval', Level::Red, [Axis::Size, Axis::Harness, Axis::Intervention],
        ];
        yield 'bohort -> Blue, Taille+Harness+Intervention ex æquo' => [
            'bohort', Level::Blue, [Axis::Size, Axis::Harness, Axis::Intervention],
        ];
        yield 'leodagan -> Green, En parallèle seul' => [
            'leodagan', Level::Green, [Axis::Parallelism],
        ];
        yield 'arthur -> Copper, Harness+Intervention ex æquo' => [
            'arthur', Level::Copper, [Axis::Harness, Axis::Intervention],
        ];
    }

    /**
     * Lines that carry an `Evidence`/`Note` pointer are always indented (docs/specs/06 §
     * Format de sortie: the pointer sits on its own line under the claim it supports); the
     * level bar, headings and prose lines are not. Filtering on indentation keeps this test
     * from asserting a pointer on lines that were never meant to carry one (the header, the
     * level bar, the blocking-axis line).
     *
     * @return list<string>
     */
    private function pointerLines(string $rendered): array
    {
        $lines = explode("\n", $rendered);

        return array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, '    ') && str_contains($line, '›'),
        ));
    }

}
