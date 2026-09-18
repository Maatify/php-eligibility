<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Decision;

enum DimensionReasonEnum: string
{
    case PASSED_ALLOW_LIST = 'PASSED_ALLOW_LIST';
    case PASSED_DENY_LIST = 'PASSED_DENY_LIST';
    case PASSED_DENY_LIST_CONTEXT_MISSING = 'PASSED_DENY_LIST_CONTEXT_MISSING';
    case DENIED_BY_RULE = 'DENIED_BY_RULE';
    case ALLOW_LIST_UNSATISFIED = 'ALLOW_LIST_UNSATISFIED';
    case ALLOW_LIST_CONTEXT_MISSING = 'ALLOW_LIST_CONTEXT_MISSING';
}
