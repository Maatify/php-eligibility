<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\ValueObject;

use JsonSerializable;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\Common\CanonicalString;

final readonly class DesiredRule implements JsonSerializable
{
    public string $dimensionValue;

    public function __construct(mixed $dimensionValue, public RuleEffectEnum $effect)
    {
        $this->dimensionValue = CanonicalString::validateDimensionValue($dimensionValue);
    }

    /** @return array{dimensionValue: string, effect: string} */
    public function jsonSerialize(): array
    {
        return [
            'dimensionValue' => $this->dimensionValue,
            'effect' => $this->effect->value,
        ];
    }
}
