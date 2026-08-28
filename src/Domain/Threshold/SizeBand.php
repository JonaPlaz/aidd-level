<?php

declare(strict_types=1);

namespace AiddLevel\Domain\Threshold;

/**
 * The four size bands of docs/specs/01-axe-taille.md § Seuils (S/M/L/XL). Distinct from
 * `Level`: the Size axis evaluator maps a band to a level (docs/specs/01-axe-taille.md §
 * Correspondance palier → niveau), this enum only names the band itself.
 */
enum SizeBand
{
    case S;
    case M;
    case L;
    case XL;
}
