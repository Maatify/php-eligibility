<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

use JsonSerializable;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Validation\CanonicalString;
use Maatify\Eligibility\Value\Subject;

final readonly class RuleReference implements JsonSerializable
{
    public string $dimensionKey;

    public string $dimensionValue;

    public function __construct(
        public Subject $subject,
        mixed $dimensionKey,
        mixed $dimensionValue,
        public RuleEffectEnum $effect,
    ) {
        $this->dimensionKey = CanonicalString::validate($dimensionKey, 'dimensionKey');
        $this->dimensionValue = CanonicalString::validate($dimensionValue, 'dimensionValue');
    }

    public static function fromRule(Rule $rule): self
    {
        return new self(
            $rule->subject,
            $rule->dimensionKey,
            $rule->dimensionValue,
            $rule->effect,
        );
    }

    public function naturalIdentity(): RuleIdentity
    {
        return new RuleIdentity(
            $this->subject->subjectType,
            $this->subject->subjectId,
            $this->dimensionKey,
            $this->dimensionValue,
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'subject' => $this->subject,
            'dimensionKey' => $this->dimensionKey,
            'dimensionValue' => $this->dimensionValue,
            'effect' => $this->effect->value,
        ];
    }
}
