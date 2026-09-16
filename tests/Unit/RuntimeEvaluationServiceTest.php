<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Application\Evaluation\EligibilityRuleEvaluator;
use Maatify\Eligibility\Application\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Decision\DimensionReasonEnum;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\InMemoryRuleRepository;
use Maatify\Eligibility\Value\Context;
use Maatify\Eligibility\Value\ContextDimension;
use Maatify\Eligibility\Value\Subject;
use Maatify\Eligibility\Value\SubjectCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeEvaluationServiceTest extends TestCase
{
    #[Test]
    public function noActiveRulesIsUnrestrictedAndInactiveRulesAreIgnored(): void
    {
        $repository = new InMemoryRuleRepository();
        $repository->seed($this->rule('EG', RuleEffectEnum::DENY)->withLifecycle(RuleLifecycleEnum::INACTIVE));

        $decision = (new EligibilityEvaluationService($repository))->decide(
            new Subject('product', '150'),
            new Context(),
        );

        self::assertTrue($decision->eligible);
        self::assertSame(DecisionReasonEnum::UNRESTRICTED, $decision->reasonCode);
        self::assertCount(0, $decision->dimensionOutcomes);
    }

    #[Test]
    public function allowMatchPassesAndReturnsMatchingAllowTrace(): void
    {
        $decision = $this->evaluate(
            new Context(ContextDimension::fromStrings('country', 'SA', 'EG')),
            $this->rule('SA', RuleEffectEnum::ALLOW),
            $this->rule('EG', RuleEffectEnum::ALLOW),
        );
        $outcome = $decision->dimensionOutcomes->items()[0];

        self::assertTrue($decision->eligible);
        self::assertSame(DimensionReasonEnum::PASSED_ALLOW_LIST, $outcome->reasonCode);
        self::assertSame(['EG', 'SA'], array_map(
            static fn ($reference): string => $reference->dimensionValue,
            $outcome->matchedRules->items(),
        ));
    }

    #[Test]
    public function allowListUnsatisfiedAndMissingContextAreDistinctFailures(): void
    {
        $unsatisfied = $this->evaluate(
            new Context(ContextDimension::fromStrings('country', 'SA')),
            $this->rule('EG', RuleEffectEnum::ALLOW),
        );
        $missing = $this->evaluate(new Context(), $this->rule('EG', RuleEffectEnum::ALLOW));

        self::assertSame(
            DimensionReasonEnum::ALLOW_LIST_UNSATISFIED,
            $unsatisfied->dimensionOutcomes->items()[0]->reasonCode,
        );
        self::assertSame(
            DimensionReasonEnum::ALLOW_LIST_CONTEXT_MISSING,
            $missing->dimensionOutcomes->items()[0]->reasonCode,
        );
        self::assertCount(0, $unsatisfied->dimensionOutcomes->items()[0]->matchedRules);
        self::assertCount(0, $missing->dimensionOutcomes->items()[0]->matchedRules);
    }

    #[Test]
    public function denyOnlyPassesWithAndWithoutContextWhenNoValueMatches(): void
    {
        $withContext = $this->evaluate(
            new Context(ContextDimension::fromStrings('country', 'EG')),
            $this->rule('SA', RuleEffectEnum::DENY),
        );
        $withoutContext = $this->evaluate(
            new Context(),
            $this->rule('SA', RuleEffectEnum::DENY),
        );

        self::assertSame(
            DimensionReasonEnum::PASSED_DENY_LIST,
            $withContext->dimensionOutcomes->items()[0]->reasonCode,
        );
        self::assertSame(
            DimensionReasonEnum::PASSED_DENY_LIST_CONTEXT_MISSING,
            $withoutContext->dimensionOutcomes->items()[0]->reasonCode,
        );
    }

    #[Test]
    public function denyMatchWinsAndTraceContainsEveryMatchingEffect(): void
    {
        $decision = $this->evaluate(
            new Context(ContextDimension::fromStrings('country', 'EG', 'SA')),
            $this->rule('SA', RuleEffectEnum::ALLOW),
            $this->rule('EG', RuleEffectEnum::DENY),
        );
        $outcome = $decision->dimensionOutcomes->items()[0];

        self::assertFalse($decision->eligible);
        self::assertSame(DimensionReasonEnum::DENIED_BY_RULE, $outcome->reasonCode);
        self::assertSame(['EG', 'SA'], array_map(
            static fn ($reference): string => $reference->dimensionValue,
            $outcome->matchedRules->items(),
        ));
    }

    #[Test]
    public function dimensionsUseAndSemanticsAndAllOutcomesRemainVisibleAfterFailure(): void
    {
        $decision = $this->evaluate(
            new Context(
                ContextDimension::fromStrings('country', 'SA'),
                ContextDimension::fromStrings('customer_type', 'vip'),
            ),
            $this->rule('EG', RuleEffectEnum::ALLOW),
            $this->rule('vip', RuleEffectEnum::ALLOW, 'customer_type'),
        );
        $outcomes = $decision->dimensionOutcomes->items();

        self::assertFalse($decision->eligible);
        self::assertSame(['country', 'customer_type'], array_map(
            static fn ($outcome): string => $outcome->dimensionKey,
            $outcomes,
        ));
        self::assertSame(DimensionReasonEnum::ALLOW_LIST_UNSATISFIED, $outcomes[0]->reasonCode);
        self::assertSame(DimensionReasonEnum::PASSED_ALLOW_LIST, $outcomes[1]->reasonCode);
    }

    #[Test]
    public function extraContextDimensionsAreIgnored(): void
    {
        $decision = $this->evaluate(
            new Context(
                ContextDimension::fromStrings('country', 'EG'),
                ContextDimension::fromStrings('extra', 'anything'),
            ),
            $this->rule('EG', RuleEffectEnum::ALLOW),
        );

        self::assertTrue($decision->eligible);
        self::assertCount(1, $decision->dimensionOutcomes);
    }

    #[Test]
    public function batchIsOrderedEquivalentToSingleAndUsesOneBulkRead(): void
    {
        $first = new Subject('product', '150');
        $second = new Subject('category', '25');
        $third = new Subject('promotion', 'summer');
        $context = new Context(ContextDimension::fromStrings('country', 'EG'));
        $repository = new InMemoryRuleRepository();
        $repository->seed(
            $this->rule('EG', RuleEffectEnum::ALLOW, 'country', 'product', '150'),
            $this->rule('SA', RuleEffectEnum::ALLOW, 'country', 'category', '25'),
        );
        $service = new EligibilityEvaluationService($repository);

        $batch = $service->decideMany(new SubjectCollection($second, $first, $third), $context);

        self::assertSame(1, $repository->bulkReadCount);
        self::assertSame([$second, $first, $third], array_map(
            static fn ($result) => $result->subject,
            $batch->items(),
        ));
        $singleRepository = new InMemoryRuleRepository();
        $singleRepository->seed(...$repository->allRules());
        $single = new EligibilityEvaluationService($singleRepository);
        foreach ($batch as $index => $result) {
            $subject = [$second, $first, $third][$index];
            self::assertSame(
                json_encode($single->decide($subject, $context)),
                json_encode($result->decision),
            );
        }
    }

    #[Test]
    public function emptyBatchReturnsEmptyResultWithoutPerSubjectWork(): void
    {
        $repository = new InMemoryRuleRepository();
        $result = (new EligibilityEvaluationService($repository))->decideMany(new SubjectCollection(), new Context());

        self::assertCount(0, $result);
        self::assertSame(1, $repository->bulkReadCount);
    }

    #[Test]
    public function evaluatorIsASeparateSharedPureComponent(): void
    {
        self::assertTrue((new \ReflectionClass(EligibilityRuleEvaluator::class))->isFinal());
        self::assertTrue((new \ReflectionClass(EligibilityEvaluationService::class))->implementsInterface(
            \Maatify\Eligibility\Application\Service\EligibilityEvaluationServiceInterface::class,
        ));
    }

    private function evaluate(\Maatify\Eligibility\Value\Context $context, Rule ...$rules): \Maatify\Eligibility\Decision\EligibilityDecision
    {
        $repository = new InMemoryRuleRepository();
        $repository->seed(...$rules);

        return (new EligibilityEvaluationService($repository))->decide(
            new Subject('product', '150'),
            $context,
        );
    }

    private function rule(
        string $value,
        RuleEffectEnum $effect,
        string $dimension = 'country',
        string $subjectType = 'product',
        string $subjectId = '150',
    ): Rule {
        return Rule::active(new Subject($subjectType, $subjectId), $dimension, $value, $effect);
    }
}
