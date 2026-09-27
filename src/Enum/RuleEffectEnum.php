<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Enum;

/** The explicit allow/deny effect applied when a Rule matches a supplied value. */
enum RuleEffectEnum: string
{
    case ALLOW = 'allow';
    case DENY = 'deny';
}
