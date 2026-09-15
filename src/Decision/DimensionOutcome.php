<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

use InvalidArgumentException;
use JsonSerializable;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Validation\CanonicalString;

final readonly class DimensionOutcome implements JsonSerializable
{
    public string $dimensionKey;

    public function __construct(
        mixed $dimensionKey,
        mixed $passed,
        public DimensionReasonEnum $reasonCode,
        public RuleReferenceCollection $matchedRules,
    ) {
        $this->dimensionKey = CanonicalString::validate($dimensionKey, 'dimensionKey');
        if (!is_bool($passed)) {
            throw new InvalidArgumentException('Dimension outcome passed state must be a boolean.');
        }

        if (
            in_array($reasonCode, [
                DimensionReasonEnum::PASSED_ALLOW_LIST,
                DimensionReasonEnum::PASSED_DENY_LIST,
                DimensionReasonEnum::PASSED_DENY_LIST_CONTEXT_MISSING,
            ], true) && !$passed
        ) {
            throw new InvalidArgumentException('A passed dimension reason requires passed=true.');
        }

        if (
            in_array($reasonCode, [
                DimensionReasonEnum::DENIED_BY_RULE,
                DimensionReasonEnum::ALLOW_LIST_UNSATISFIED,
                DimensionReasonEnum::ALLOW_LIST_CONTEXT_MISSING,
            ], true) && $passed
        ) {
            throw new InvalidArgumentException('A denied dimension reason requires passed=false.');
        }

        if ($reasonCode === DimensionReasonEnum::PASSED_ALLOW_LIST) {
            if ($matchedRules->count() === 0 || $matchedRules->hasEffect(RuleEffectEnum::DENY)) {
                throw new InvalidArgumentException('PASSED_ALLOW_LIST requires matching ALLOW Rules only.');
            }
        }

        if ($reasonCode === DimensionReasonEnum::DENIED_BY_RULE) {
            if ($matchedRules->count() === 0 || !$matchedRules->hasEffect(RuleEffectEnum::DENY)) {
                throw new InvalidArgumentException('DENIED_BY_RULE requires at least one matching DENY Rule.');
            }
        }

        if (
            in_array($reasonCode, [
                DimensionReasonEnum::PASSED_DENY_LIST,
                DimensionReasonEnum::PASSED_DENY_LIST_CONTEXT_MISSING,
                DimensionReasonEnum::ALLOW_LIST_UNSATISFIED,
                DimensionReasonEnum::ALLOW_LIST_CONTEXT_MISSING,
            ], true) && $matchedRules->count() !== 0
        ) {
            throw new InvalidArgumentException('This dimension reason requires an empty matched Rule trace.');
        }

        foreach ($matchedRules as $matchedRule) {
            if ($matchedRule->dimensionKey !== $this->dimensionKey) {
                throw new InvalidArgumentException('Matched Rule references must belong to the outcome dimension.');
            }
        }

        $this->passed = $passed;
    }

    public bool $passed;

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'dimensionKey' => $this->dimensionKey,
            'passed' => $this->passed,
            'reasonCode' => $this->reasonCode->value,
            'matchedRules' => $this->matchedRules,
        ];
    }
}
