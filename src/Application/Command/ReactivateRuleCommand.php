<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Command;

use Maatify\Eligibility\Rule\RuleIdentity;

final readonly class ReactivateRuleCommand
{
    public function __construct(public RuleIdentity $identity)
    {
    }
}
