<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Axis\Support;

use AiddLevel\Domain\Pointer;

/**
 * Every axis evaluator (Size, Intervention, Parallelism, Harness) reads its signal from
 * `git-activity.json` and builds a `Pointer` into it; each used to repeat the file name as
 * its own private constant. One shared factory, one literal.
 */
final class GitActivityPointer
{
    public const string FILE = 'git-activity.json';

    public static function of(string $field, string $value): Pointer
    {
        return new Pointer(self::FILE, $field, $value);
    }
}
