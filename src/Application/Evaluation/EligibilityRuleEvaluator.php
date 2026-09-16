<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Evaluation;

use Maatify\Eligibility\Decision\DimensionOutcome;
use Maatify\Eligibility\Decision\DimensionOutcomeCollection;
use Maatify\Eligibility\Decision\DimensionReasonEnum;
use Maatify\Eligibility\Decision\EligibilityDecision;
use Maatify\Eligibility\Decision\RuleReference;
use Maatify\Eligibility\Decision\RuleReferenceCollection;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Value\Context;
use Maatify\Eligibility\Value\Subject;

/**
 * @internal Pure evaluation logic shared by single and bulk service paths.
 */
final class EligibilityRuleEvaluator
{
    public function evaluate(Subject $subject, Context $context, RuleCollection $rules): EligibilityDecision
    {
        /** @var array<string, list<Rule>> $rulesByDimension */
        $rulesByDimension = [];

        foreach ($rules as $rule) {
            if (
                $rule->lifecycle !== RuleLifecycleEnum::ACTIVE
                || $rule->subject->subjectType !== $subject->subjectType
                || $rule->subject->subjectId !== $subject->subjectId
            ) {
                continue;
            }

            $rulesByDimension[$this->stringKey($rule->dimensionKey)][] = $rule;
        }

        if ($rulesByDimension === []) {
            return EligibilityDecision::unrestricted();
        }

        $outcomes = [];
        foreach ($rulesByDimension as $dimensionRules) {
            $dimensionKey = $dimensionRules[0]->dimensionKey;
            $outcomes[] = $this->evaluateDimension($dimensionKey, $dimensionRules, $context);
        }

        $dimensionOutcomes = new DimensionOutcomeCollection(...$outcomes);

        return $dimensionOutcomes->allPassed()
            ? EligibilityDecision::eligible($dimensionOutcomes)
            : EligibilityDecision::denied($dimensionOutcomes);
    }

    /**
     * @param list<Rule> $rules
     */
    private function evaluateDimension(
        string $dimensionKey,
        array $rules,
        Context $context,
    ): DimensionOutcome {
        $contextDimension = $context->getDimension($dimensionKey);
        $hasAllowRule = false;
        $hasDenyRule = false;
        $matchingAllowCount = 0;
        $matchingDenyCount = 0;
        $matchingReferences = [];

        foreach ($rules as $rule) {
            if ($rule->effect === RuleEffectEnum::ALLOW) {
                $hasAllowRule = true;
            } else {
                $hasDenyRule = true;
            }

            if (
                $contextDimension === null
                || !$contextDimension->values->contains($rule->dimensionValue)
            ) {
                continue;
            }

            $matchingReferences[] = RuleReference::fromRule($rule);
            if ($rule->effect === RuleEffectEnum::ALLOW) {
                $matchingAllowCount++;
            } else {
                $matchingDenyCount++;
            }
        }

        if ($matchingDenyCount > 0) {
            return new DimensionOutcome(
                $dimensionKey,
                false,
                DimensionReasonEnum::DENIED_BY_RULE,
                new RuleReferenceCollection(...$matchingReferences),
            );
        }

        if ($hasAllowRule) {
            if ($contextDimension === null) {
                return new DimensionOutcome(
                    $dimensionKey,
                    false,
                    DimensionReasonEnum::ALLOW_LIST_CONTEXT_MISSING,
                    new RuleReferenceCollection(),
                );
            }

            if ($matchingAllowCount > 0) {
                return new DimensionOutcome(
                    $dimensionKey,
                    true,
                    DimensionReasonEnum::PASSED_ALLOW_LIST,
                    new RuleReferenceCollection(...$matchingReferences),
                );
            }

            return new DimensionOutcome(
                $dimensionKey,
                false,
                DimensionReasonEnum::ALLOW_LIST_UNSATISFIED,
                new RuleReferenceCollection(),
            );
        }

        return new DimensionOutcome(
            $dimensionKey,
            true,
            $contextDimension === null
                ? DimensionReasonEnum::PASSED_DENY_LIST_CONTEXT_MISSING
                : DimensionReasonEnum::PASSED_DENY_LIST,
            new RuleReferenceCollection(),
        );
    }

    private function stringKey(string $value): string
    {
        return strlen($value) . ':' . $value;
    }
}
