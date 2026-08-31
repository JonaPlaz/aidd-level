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
 * End to end: real profile folder → `ApplicationFactory`'s handler → `TextRenderer` →
 * rendered text. Fixes the four verdicts `docs/calibration.md` proves against the
 * organizers' attributed levels, so a repreneur who moves a threshold sees immediately what
 * breaks (docs/calibration.md § Reproduction). Duplicates `EvaluateProfileHandlerTest`'s
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

        self::assertGreaterThan(
            0,
            $this->pointedLineCount($rendered),
            'Le bloc "Les preuves des axes qui limitent" ne cite aucune ligne de preuve pointée.',
        );
    }

    /**
     * docs/specs/06-sortie-et-progression.md § 12, test 14: the one real-profile snapshot the
     * chantier fixes, `arthur` — the profile `docs/sortie.md` and `README.md` both recopy, so
     * this test is the guard-rail for review point 10 ("la doc suit le code"). The three other
     * calibrated profiles stay covered by `rendersTheCalibratedLevelAndCappingAxes()` above,
     * without their own snapshot (§ 12: figer les quatre ferait payer chaque reformulation de
     * phrase quatre fois, sans rien prouver de plus).
     */
    #[Test]
    public function rendersArthurExactlyLikeTheFixedSnapshot(): void
    {
        $handler = ApplicationFactory::createHandler();
        $renderer = ApplicationFactory::createRenderer();

        $assessment = $handler->handle(new EvaluateProfile(self::PROFILES_DIR.'/arthur'));
        $rendered = $renderer->render($assessment);

        self::assertSame(
            file_get_contents(__DIR__.'/../expected/arthur.txt'),
            $rendered,
        );
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
     * Counts the pointer-bearing lines inside the "Les preuves des axes qui limitent" block (docs/specs/06 §
     * Format de sortie): every axis headline sits at a two-space indent, every `Evidence`
     * pointer under it one level deeper, at four spaces. Candidate lines are selected by that
     * indentation alone, never by the presence of `›` (Codex review of PR #25, remark 3 — a
     * selection that already required `›` would make the assertion that follows tautological);
     * only the count of the ones that do carry `›` is asserted, since a same-indent line can
     * legitimately carry none (e.g. a `Range` confidence's "fourchette : entre … et …" line).
     */
    private function pointedLineCount(string $rendered): int
    {
        $section = $this->cappingAxesSection($rendered);
        $lines = explode("\n", $section);

        $indented = array_filter($lines, static fn (string $line): bool => str_starts_with($line, '    '));

        return \count(array_filter($indented, static fn (string $line): bool => str_contains($line, '›')));
    }

    /**
     * The "Les preuves des axes qui limitent" block only, up to the next known block heading — not the first
     * blank line, which the block also uses between its own axis entries
     * (docs/specs/06-sortie-et-progression.md § 5.2).
     */
    private function cappingAxesSection(string $rendered): string
    {
        $heading = 'Les preuves des axes qui limitent';
        $start = strpos($rendered, $heading);
        self::assertNotFalse($start, sprintf('Bloc "%s" absent du rendu.', $heading));

        $end = null;
        foreach (['Déjà acquis', "Comment monter d'un cran", 'Notes'] as $nextHeading) {
            $position = strpos($rendered, "\n\n".$nextHeading, $start);
            if (false !== $position && (null === $end || $position < $end)) {
                $end = $position;
            }
        }

        return null !== $end ? substr($rendered, $start, $end - $start) : substr($rendered, $start);
    }
}
