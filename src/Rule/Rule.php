<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule;

use JsonSerializable;
use Maatify\Eligibility\Validation\CanonicalString;
use Maatify\Eligibility\Value\Subject;

final readonly class Rule implements JsonSerializable
{
    public string $dimensionKey;

    public string $dimensionValue;

    public function __construct(
        public Subject $subject,
        mixed $dimensionKey,
        mixed $dimensionValue,
        public RuleEffectEnum $effect,
        public RuleLifecycleEnum $lifecycle = RuleLifecycleEnum::ACTIVE,
    ) {
        $this->dimensionKey = CanonicalString::validate($dimensionKey, 'dimensionKey');
        $this->dimensionValue = CanonicalString::validate($dimensionValue, 'dimensionValue');
    }

    public static function active(
        Subject $subject,
        mixed $dimensionKey,
        mixed $dimensionValue,
        RuleEffectEnum $effect,
    ): self {
        return new self($subject, $dimensionKey, $dimensionValue, $effect);
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

    public function withEffect(RuleEffectEnum $effect): self
    {
        return new self(
            $this->subject,
            $this->dimensionKey,
            $this->dimensionValue,
            $effect,
            $this->lifecycle,
        );
    }

    public function withLifecycle(RuleLifecycleEnum $lifecycle): self
    {
        return new self(
            $this->subject,
            $this->dimensionKey,
            $this->dimensionValue,
            $this->effect,
            $lifecycle,
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
            'lifecycle' => $this->lifecycle->value,
        ];
    }
}
