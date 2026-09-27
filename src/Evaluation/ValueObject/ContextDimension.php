<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\ValueObject;

use JsonSerializable;
use Maatify\Eligibility\Common\CanonicalString;

/**
 * One Host-defined dimension and its one-or-more exact canonical values.
 *
 * An absent dimension is represented by omitting this object from Context; an
 * explicitly present empty value set is rejected by ContextValueCollection.
 */
final readonly class ContextDimension implements JsonSerializable
{
    public string $dimensionKey;

    /** Associates one canonical key with a non-empty, duplicate-free value collection. */
    public function __construct(mixed $dimensionKey, public ContextValueCollection $values)
    {
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
    }

    /** Builds the typed collection form while preserving strict canonical validation. */
    public static function fromStrings(mixed $dimensionKey, mixed ...$values): self
    {
        return new self(
            $dimensionKey,
            new ContextValueCollection(...array_map(
                static fn(mixed $value): ContextValue => new ContextValue($value),
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
