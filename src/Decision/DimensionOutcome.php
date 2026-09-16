<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
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
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
        if (!is_bool($passed)) {
            throw new InvalidEligibilityInputException('Dimension outcome passed state must be a boolean.');
        }

        if (
            in_array($reasonCode, [
                DimensionReasonEnum::PASSED_ALLOW_LIST,
                DimensionReasonEnum::PASSED_DENY_LIST,
                DimensionReasonEnum::PASSED_DENY_LIST_CONTEXT_MISSING,
            ], true) && !$passed
        ) {
            throw new InvalidEligibilityInputException('A passed dimension reason requires passed=true.');
        }

        if (
            in_array($reasonCode, [
                DimensionReasonEnum::DENIED_BY_RULE,
                DimensionReasonEnum::ALLOW_LIST_UNSATISFIED,
                DimensionReasonEnum::ALLOW_LIST_CONTEXT_MISSING,
            ], true) && $passed
        ) {
            throw new InvalidEligibilityInputException('A denied dimension reason requires passed=false.');
        }

        if ($reasonCode === DimensionReasonEnum::PASSED_ALLOW_LIST) {
            if ($matchedRules->count() === 0 || $matchedRules->hasEffect(RuleEffectEnum::DENY)) {
                throw new InvalidEligibilityInputException('PASSED_ALLOW_LIST requires matching ALLOW Rules only.');
            }
        }

        if ($reasonCode === DimensionReasonEnum::DENIED_BY_RULE) {
            if ($matchedRules->count() === 0 || !$matchedRules->hasEffect(RuleEffectEnum::DENY)) {
                throw new InvalidEligibilityInputException('DENIED_BY_RULE requires at least one matching DENY Rule.');
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
            throw new InvalidEligibilityInputException('This dimension reason requires an empty matched Rule trace.');
        }

        $traceSubjectType = null;
        $traceSubjectId = null;
        foreach ($matchedRules as $matchedRule) {
            if ($matchedRule->dimensionKey !== $this->dimensionKey) {
                throw new InvalidEligibilityInputException('Matched Rule references must belong to the outcome dimension.');
            }

            if ($traceSubjectType === null) {
                $traceSubjectType = $matchedRule->subjectType;
                $traceSubjectId = $matchedRule->subjectId;
                continue;
            }

            if (
                $matchedRule->subjectType !== $traceSubjectType
                || $matchedRule->subjectId !== $traceSubjectId
            ) {
                throw new InvalidEligibilityInputException(
                    'Matched Rule references must belong to the same subject.',
                );
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
