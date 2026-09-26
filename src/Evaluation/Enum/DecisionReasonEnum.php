<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Enum;

enum DecisionReasonEnum: string
{
    case UNRESTRICTED = 'UNRESTRICTED';
    case ELIGIBLE = 'ELIGIBLE';
    case DENIED = 'DENIED';
}
