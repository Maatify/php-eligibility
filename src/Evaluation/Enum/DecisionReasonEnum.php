<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Enum;

/** Aggregate reason explaining whether a Subject is unrestricted, eligible, or denied. */
enum DecisionReasonEnum: string
{
    case UNRESTRICTED = 'UNRESTRICTED';
    case ELIGIBLE = 'ELIGIBLE';
    case DENIED = 'DENIED';
}
