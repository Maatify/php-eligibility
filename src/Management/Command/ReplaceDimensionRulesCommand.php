<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Management\ValueObject\DesiredRuleCollection;
use Maatify\Eligibility\ValueObject\Subject;

/**
 * Desired-state command scoped to one Subject and one dimension.
 *
 * The desired collection is duplicate-free and canonicalized by value; an
 * empty collection is meaningful and removes/deactivates every active Rule in
 * the selected dimension during the transactional replacement.
 */
final readonly class ReplaceDimensionRulesCommand
{
    public string $dimensionKey;

    /** Validates the target dimension; an empty desired collection means no active values remain. */
    public function __construct(
        public Subject $subject,
        mixed $dimensionKey,
        public DesiredRuleCollection $desiredRules,
    ) {
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
    }
}
