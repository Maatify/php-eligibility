<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Enum;

enum RuleLifecycleEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
