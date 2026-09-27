<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Enum;

/** Persistence lifecycle state; only active Rules participate in evaluation. */
enum RuleLifecycleEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
