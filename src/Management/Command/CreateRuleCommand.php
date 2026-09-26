<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\ValueObject\Subject;

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
