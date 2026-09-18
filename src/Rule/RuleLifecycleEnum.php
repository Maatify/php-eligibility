<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule;

enum RuleLifecycleEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
