<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

/**
 * Band boundaries for the Size axis (docs/specs/01-axe-taille.md § Seuils). Files decide
 * first (S/L are defined as structural — "multi-étapes", "multi-modules" — so file count
 * fits the definition); lines are the fallback when median_files_changed is absent or zero.
 *
 * Neither the grid, the AIDD framework nor the AIDD manifesto give these bands: assumed
 * adaptation, not sourced by them. Files S/M come from a pre-AI/post-AI median lines split
 * (Brodzinski, 2026: ~66 then 210 lines) reused as the M/L split for files by proximity; L/XL
 * reuses the Salesforce "20 files and 1000 lines" threshold where code review breaks down.
 * Validated against the four supplied profiles (2, 7, 13, 29 files → S, M, L, XL).
 */
final class SizeThresholds
{
    // S band: adaptation assumed, not sourced — see class docblock.
    public const int FILES_S_MIN = 1;
    public const int FILES_S_MAX = 2;

    // M band: pre-AI/post-AI median lines split (Brodzinski, 2026) reused for file count.
    public const int FILES_M_MIN = 3;
    public const int FILES_M_MAX = 8;

    // L band: Salesforce "20 files, 1000 lines" review-breakdown threshold.
    public const int FILES_L_MIN = 9;
    public const int FILES_L_MAX = 20;

    // XL band: above the Salesforce review-breakdown threshold.
    public const int FILES_XL_MIN = 21;

    // S band (lines fallback): pre-AI median ≈ 66 lines (Brodzinski, 2026), rounded down.
    public const int LINES_S_MAX = 60;

    // M band (lines fallback): post-AI median 210 lines (Brodzinski, 2026).
    public const int LINES_M_MIN = 61;
    public const int LINES_M_MAX = 210;

    // L band (lines fallback): Salesforce "20 files, 1000 lines" review-breakdown threshold.
    public const int LINES_L_MIN = 211;
    public const int LINES_L_MAX = 1000;

    // XL band (lines fallback): above the Salesforce review-breakdown threshold.
    public const int LINES_XL_MIN = 1001;

    /**
     * The band for `pull_requests.median_files_changed`, the primary signal
     * (docs/specs/01-axe-taille.md § Signal).
     */
    public static function bandForFiles(float $files): SizeBand
    {
        return match (true) {
            $files <= self::FILES_S_MAX => SizeBand::S,
            $files <= self::FILES_M_MAX => SizeBand::M,
            $files <= self::FILES_L_MAX => SizeBand::L,
            default => SizeBand::XL,
        };
    }

    /**
     * The band for `pull_requests.median_lines_changed`, the fallback signal used only when
     * the file count is absent or zero (docs/specs/01-axe-taille.md § Signal).
     */
    public static function bandForLines(float $lines): SizeBand
    {
        return match (true) {
            $lines <= self::LINES_S_MAX => SizeBand::S,
            $lines <= self::LINES_M_MAX => SizeBand::M,
            $lines <= self::LINES_L_MAX => SizeBand::L,
            default => SizeBand::XL,
        };
    }
}
