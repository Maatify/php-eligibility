<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule;

use JsonSerializable;
use Maatify\Eligibility\Common\Validation\CanonicalString;

final readonly class RuleIdentity implements JsonSerializable
{
    public string $subjectType;

    public string $subjectId;

    public string $dimensionKey;

    public string $dimensionValue;

    public function __construct(
        mixed $subjectType,
        mixed $subjectId,
        mixed $dimensionKey,
        mixed $dimensionValue,
    ) {
        $this->subjectType = CanonicalString::validateSubjectType($subjectType);
        $this->subjectId = CanonicalString::validateSubjectId($subjectId);
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
        $this->dimensionValue = CanonicalString::validateDimensionValue($dimensionValue);
    }

    public function equals(self $other): bool
    {
        return $this->subjectType === $other->subjectType
            && $this->subjectId === $other->subjectId
            && $this->dimensionKey === $other->dimensionKey
            && $this->dimensionValue === $other->dimensionValue;
    }

    /** @return array{subjectType: string, subjectId: string, dimensionKey: string, dimensionValue: string} */
    public function jsonSerialize(): array
    {
        return [
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
            'dimensionKey' => $this->dimensionKey,
            'dimensionValue' => $this->dimensionValue,
        ];
    }
}
