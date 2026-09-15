<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Value;

use JsonSerializable;
use Maatify\Eligibility\Validation\CanonicalString;

final readonly class ContextValue implements JsonSerializable, \Stringable
{
    public string $value;

    public function __construct(mixed $value)
    {
        $this->value = CanonicalString::validate($value, 'contextValue');
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
