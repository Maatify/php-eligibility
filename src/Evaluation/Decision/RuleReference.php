<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Decision;

use JsonSerializable;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Common\Validation\CanonicalString;

final readonly class RuleReference implements JsonSerializable
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
        public RuleEffectEnum $effect,
    ) {
        $this->subjectType = CanonicalString::validateSubjectType($subjectType);
        $this->subjectId = CanonicalString::validateSubjectId($subjectId);
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
        $this->dimensionValue = CanonicalString::validateDimensionValue($dimensionValue);
    }

    public static function fromRule(Rule $rule): self
    {
        return new self(
            $rule->subject->subjectType,
            $rule->subject->subjectId,
            $rule->dimensionKey,
            $rule->dimensionValue,
            $rule->effect,
        );
    }

    public function naturalIdentity(): RuleIdentity
    {
        return new RuleIdentity(
            $this->subjectType,
            $this->subjectId,
            $this->dimensionKey,
            $this->dimensionValue,
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
            'dimensionKey' => $this->dimensionKey,
            'dimensionValue' => $this->dimensionValue,
            'effect' => $this->effect->value,
        ];
    }
}
