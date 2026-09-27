<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\ValueObject;

use JsonSerializable;
use Maatify\Eligibility\Common\CanonicalString;

/**
 * Immutable canonical value supplied for one evaluation dimension.
 *
 * The original validated string is preserved exactly; no semantic normalization
 * is performed before matching.
 */
final readonly class ContextValue implements JsonSerializable, \Stringable
{
    public string $value;

    /**
     * Validates the input using the exact canonical dimension-value contract
     * and preserves it without semantic normalization; invalid input raises
     * InvalidEligibilityInputException.
     */
    public function __construct(mixed $value)
    {
        $this->value = CanonicalString::validateDimensionValue($value, 'contextValue');
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
