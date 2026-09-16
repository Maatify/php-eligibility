<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Command;

use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Validation\CanonicalString;
use Maatify\Eligibility\Value\Subject;

final readonly class CreateRuleCommand
{
    public string $dimensionKey;

    public string $dimensionValue;

    public function __construct(
        public Subject $subject,
        mixed $dimensionKey,
        mixed $dimensionValue,
        public RuleEffectEnum $effect,
    ) {
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
        $this->dimensionValue = CanonicalString::validateDimensionValue($dimensionValue);
    }
}
