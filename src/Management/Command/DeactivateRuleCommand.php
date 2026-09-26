<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\ValueObject\RuleIdentity;

/** Requests lifecycle deactivation of an existing Rule identity. */
final readonly class DeactivateRuleCommand
{
    public function __construct(public RuleIdentity $identity) {}
}
