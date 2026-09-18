<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\Rule\RuleIdentity;

final readonly class DeactivateRuleCommand
{
    public function __construct(public RuleIdentity $identity)
    {
    }
}
