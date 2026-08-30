<?php

declare(strict_types=1);

namespace AiddLevel\Tests\Calibration;

use AiddLevel\Application\EvaluateProfile;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Domain\Axis;
use AiddLevel\Domain\Level;
use AiddLevel\Infrastructure\Console\ApplicationFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The two profiles upstream shipped without a level (`ai-driven-dev/laivel-up` commit
 * `b5e9661`, 2026-08-30): they exist to try the tool on what it has not seen. Their
 * verdicts here are the tool's own, not an organizer's — these tests freeze behavior, they
 * are not a calibration proof (docs/calibration.md § Profils sans niveau).
 */
final class UnattributedProfilesTest extends TestCase
{
    private const string PROFILES_DIR = __DIR__.'/../../profiles';

    #[Test]
    public function venecWithNoGitActivityIsNotAssessableAndStillReadsTheIdentity(): void
    {
        $assessment = ApplicationFactory::createHandler()->handle(new EvaluateProfile(self::PROFILES_DIR.'/venec'));

        self::assertSame(AssessmentStatus::NotAssessable, $assessment->status);
        self::assertNull($assessment->level);
        self::assertNotNull($assessment->identity);
        self::assertSame('venec', $assessment->identity->id);
    }

    #[Test]
    public function lancelotIsRedCappedByInterventionAlone(): void
    {
        $assessment = ApplicationFactory::createHandler()->handle(new EvaluateProfile(self::PROFILES_DIR.'/lancelot'));

        self::assertSame(AssessmentStatus::Evaluated, $assessment->status);
        self::assertSame(Level::Red, $assessment->level);
        self::assertSame([Axis::Intervention], $assessment->cappingAxes);
    }
}
