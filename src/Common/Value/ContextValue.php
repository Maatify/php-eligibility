<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Common\Value;

use JsonSerializable;
use Maatify\Eligibility\Common\Validation\CanonicalString;

final readonly class ContextValue implements JsonSerializable, \Stringable
{
    public string $value;

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
