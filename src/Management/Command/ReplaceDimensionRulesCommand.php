<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Management\ValueObject\DesiredRuleCollection;
use Maatify\Eligibility\ValueObject\Subject;

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
