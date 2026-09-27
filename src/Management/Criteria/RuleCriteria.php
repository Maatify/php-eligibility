<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Criteria;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\ValueObject\Subject;

/**
 * Paginated management query for one Subject, optionally narrowed by dimension,
 * lifecycle, and effect. A null filter omits that condition from the query.
 */
final readonly class RuleCriteria
{
    public ?string $dimensionKey;

    /** Applies optional filters; pagination is supplied separately as a PageRequest. */
    public function __construct(
        public Subject $subject,
        mixed $dimensionKey = null,
        public ?RuleLifecycleEnum $lifecycle = null,
        public ?RuleEffectEnum $effect = null,
    ) {
        $this->dimensionKey = $dimensionKey === null
            ? null
            : CanonicalString::validateDimensionKey($dimensionKey);
    }
}
