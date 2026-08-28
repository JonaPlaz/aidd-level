<?php

declare(strict_types=1);

namespace AiddLevel\Domain;

/**
 * The three output statuses of an assessment (docs/specs/05-robustesse.md § Trois statuts de sortie).
 */
enum AssessmentStatus
{
    /** Gate passed, every axis has enough sample: level, capping axis, gesture. */
    case Evaluated;

    /** Gate passed, insufficient sample on at least one axis: a range per axis, a global floor and ceiling. */
    case LowConfidence;

    /** Gate broken: a named prerequisite, what could be read anyway, a technical lead. */
    case NotAssessable;
}
