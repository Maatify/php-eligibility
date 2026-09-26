<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\ValueObject\RuleIdentity;

final readonly class ReactivateRuleCommand
{
    public function __construct(public RuleIdentity $identity) {}
}
