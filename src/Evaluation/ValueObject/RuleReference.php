<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\ValueObject;

use JsonSerializable;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Common\CanonicalString;

/**
 * Immutable evaluation trace projection of a matched Rule.
 *
 * It preserves the Rule natural identity and effect needed to explain a
 * dimension outcome without exposing persistence lifecycle details.
 */
final readonly class RuleReference implements JsonSerializable
{
    public string $subjectType;

    public string $subjectId;

    public string $dimensionKey;

    public string $dimensionValue;

    /** Constructs a trace reference from canonical identity components and effect. */
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

    /** Projects a Rule into the public evaluation evidence shape. */
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
