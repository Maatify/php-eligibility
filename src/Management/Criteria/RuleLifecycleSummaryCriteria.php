<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Criteria;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\ValueObject\Subject;

/**
 * Selects the lifecycle Rule-count summary for one Subject, optionally narrowed
 * to one exact dimension. A null dimension summarizes every Rule for the Subject.
 */
final readonly class RuleLifecycleSummaryCriteria
{
    public ?string $dimensionKey;

    /**
     * Selects lifecycle counts for the Subject. A null dimension key, including
     * the default, summarizes all Rules; a non-null key is canonically
     * validated and narrows the summary to that exact dimension. Invalid
     * non-null input raises InvalidEligibilityInputException.
     */
    public function __construct(
        public Subject $subject,
        mixed $dimensionKey = null,
    ) {
        $this->dimensionKey = $dimensionKey === null
            ? null
            : CanonicalString::validateDimensionKey($dimensionKey);
    }
}
