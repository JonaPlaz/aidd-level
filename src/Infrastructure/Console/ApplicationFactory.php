<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Console;

use AiddLevel\Application\EvaluateProfileHandler;
use AiddLevel\Domain\Axis\Harness\HarnessEvaluator;
use AiddLevel\Domain\Axis\Intervention\InterventionEvaluator;
use AiddLevel\Domain\Axis\Parallelism\ParallelismEvaluator;
use AiddLevel\Domain\Axis\Size\SizeEvaluator;
use AiddLevel\Domain\Progression\RecommendationPolicy;
use AiddLevel\Infrastructure\Profile\DirectoryProfileSource;
use AiddLevel\Infrastructure\Render\TextRenderer;
use Symfony\Component\Console\Application;

/**
 * Wires the whole application by hand (docs/specs/00-vue-ensemble.md § 4.4: no DI container,
 * a wiring class is enough for four evaluators). `EvaluateProfileHandler::$evaluators` is
 * built here in the order the spec fixes (docs/specs/00-vue-ensemble.md § 2: Taille, Harness,
 * Intervention, En parallèle) — an order the domain itself does not care about (`LevelRule`
 * takes a minimum over the list), but keeping the declared order readable here spares any
 * reader from wondering whether it matters.
 */
final class ApplicationFactory
{
    public static function createCommand(): EvaluateCommand
    {
        return new EvaluateCommand(self::createHandler(), self::createRenderer());
    }

    /**
     * Exposed separately so a test can drive the exact production wiring end to end
     * (`tests/Calibration/CalibrationTest.php`) without reaching into `EvaluateCommand`'s
     * private properties.
     */
    public static function createHandler(): EvaluateProfileHandler
    {
        return new EvaluateProfileHandler(
            new DirectoryProfileSource(),
            [
                new SizeEvaluator(),
                new HarnessEvaluator(),
                new InterventionEvaluator(),
                new ParallelismEvaluator(),
            ],
            new RecommendationPolicy(),
        );
    }

    public static function createRenderer(): TextRenderer
    {
        return new TextRenderer();
    }

    public static function createConsoleApplication(): Application
    {
        $application = new Application('aidd-level', '0.1.0');
        $application->add(self::createCommand());

        return $application;
    }
}
