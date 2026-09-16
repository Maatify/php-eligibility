<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

enum DecisionReasonEnum: string
{
    case UNRESTRICTED = 'UNRESTRICTED';
    case ELIGIBLE = 'ELIGIBLE';
    case DENIED = 'DENIED';
}
