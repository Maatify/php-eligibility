<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Value;

use JsonSerializable;
use Maatify\Eligibility\Common\Validation\CanonicalString;

final readonly class ContextDimension implements JsonSerializable
{
    public string $dimensionKey;

    public function __construct(mixed $dimensionKey, public ContextValueCollection $values)
    {
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
    }

    public static function fromStrings(mixed $dimensionKey, mixed ...$values): self
    {
        return new self(
            $dimensionKey,
            new ContextValueCollection(...array_map(
                static fn (mixed $value): ContextValue => new ContextValue($value),
                $values,
            )),
        );
    }

    /** @return array{dimensionKey: string, values: list<string>} */
    public function jsonSerialize(): array
    {
        return [
            'dimensionKey' => $this->dimensionKey,
            'values' => $this->values->values(),
        ];
    }
}
