<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Enum;

enum RuleEffectEnum: string
{
    case ALLOW = 'allow';
    case DENY = 'deny';
}
