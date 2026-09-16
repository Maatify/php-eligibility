<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Query;

use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Validation\CanonicalString;
use Maatify\Eligibility\Value\Subject;

final readonly class RuleCriteria
{
    public const DEFAULT_MAX_RESULTS = 100;

    public const MAX_MAX_RESULTS = 500;

    public ?string $dimensionKey;

    public int $maxResults;

    public function __construct(
        public Subject $subject,
        mixed $dimensionKey = null,
        public ?RuleLifecycleEnum $lifecycle = null,
        mixed $maxResults = self::DEFAULT_MAX_RESULTS,
    ) {
        $this->dimensionKey = $dimensionKey === null
            ? null
            : CanonicalString::validate($dimensionKey, 'dimensionKey');

        if (
            !is_int($maxResults)
            || $maxResults < 1
            || $maxResults > self::MAX_MAX_RESULTS
        ) {
            throw new InvalidEligibilityInputException(
                sprintf(
                    'maxResults must be an integer between 1 and %d.',
                    self::MAX_MAX_RESULTS,
                ),
            );
        }

        $this->maxResults = $maxResults;
    }
}
