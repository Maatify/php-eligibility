<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Command;

use Maatify\Eligibility\Validation\CanonicalString;
use Maatify\Eligibility\Value\Subject;

final readonly class ReplaceDimensionRulesCommand
{
    public string $dimensionKey;

    public function __construct(
        public Subject $subject,
        mixed $dimensionKey,
        public DesiredRuleCollection $desiredRules,
    ) {
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
    }
}
