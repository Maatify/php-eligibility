<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Command;

use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;

final readonly class UpdateRuleEffectCommand
{
    public function __construct(
        public RuleIdentity $identity,
        public RuleEffectEnum $effect,
    ) {
    }
}
