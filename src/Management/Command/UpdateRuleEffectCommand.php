<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\RuleIdentity;

/** Requests changing the effect of an existing Rule without changing its identity. */
final readonly class UpdateRuleEffectCommand
{
    public function __construct(
        public RuleIdentity $identity,
        public RuleEffectEnum $effect,
    ) {}
}
