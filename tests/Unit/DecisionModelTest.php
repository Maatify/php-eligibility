<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Evaluation\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Evaluation\Decision\DimensionOutcome;
use Maatify\Eligibility\Evaluation\Decision\DimensionOutcomeCollection;
use Maatify\Eligibility\Evaluation\Decision\DimensionReasonEnum;
use Maatify\Eligibility\Evaluation\Decision\EligibilityDecision;
use Maatify\Eligibility\Evaluation\Decision\RuleReference;
use Maatify\Eligibility\Evaluation\Decision\RuleReferenceCollection;
use Maatify\Eligibility\Exception\EligibilityExceptionInterface;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Common\Value\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DecisionModelTest extends TestCase
{
    #[Test]
    public function unrestrictedDecisionHasNoOutcomes(): void
    {
        $decision = EligibilityDecision::unrestricted();

        self::assertTrue($decision->eligible);
        self::assertSame(DecisionReasonEnum::UNRESTRICTED, $decision->reasonCode);
        self::assertCount(0, $decision->dimensionOutcomes);
    }

    #[Test]
    public function eligibleDecisionRequiresAllPassingOutcomes(): void
    {
        $outcome = new DimensionOutcome(
            'country',
            true,
            DimensionReasonEnum::PASSED_ALLOW_LIST,
            new RuleReferenceCollection($this->reference('EG', RuleEffectEnum::ALLOW)),
        );

        $decision = EligibilityDecision::eligible(new DimensionOutcomeCollection($outcome));

        self::assertTrue($decision->eligible);
        self::assertSame(DecisionReasonEnum::ELIGIBLE, $decision->reasonCode);
    }

    #[Test]
    public function deniedDecisionRetainsEveryOutcomeAndTrace(): void
    {
        $country = new DimensionOutcome(
            'country',
            false,
            DimensionReasonEnum::DENIED_BY_RULE,
            new RuleReferenceCollection(
                $this->reference('SA', RuleEffectEnum::ALLOW),
                $this->reference('EG', RuleEffectEnum::DENY),
            ),
        );
        $customerType = new DimensionOutcome(
            'customer_type',
            true,
            DimensionReasonEnum::PASSED_DENY_LIST,
            new RuleReferenceCollection(),
        );

        $decision = EligibilityDecision::denied(new DimensionOutcomeCollection($customerType, $country));
        $outcomes = $decision->dimensionOutcomes->items();
        $references = $country->matchedRules->items();

        self::assertFalse($decision->eligible);
        self::assertSame('country', $outcomes[0]->dimensionKey);
        self::assertSame('EG', $references[0]->dimensionValue);
        self::assertSame('SA', $references[1]->dimensionValue);
    }

    #[Test]
    public function invalidDecisionCombinationsAreRejected(): void
    {
        $outcome = new DimensionOutcome(
            'country',
            true,
            DimensionReasonEnum::PASSED_DENY_LIST,
            new RuleReferenceCollection(),
        );

        $this->expectException(InvalidEligibilityInputException::class);

        new EligibilityDecision(
            true,
            DecisionReasonEnum::UNRESTRICTED,
            new DimensionOutcomeCollection($outcome),
        );
    }

    #[Test]
    public function invalidDimensionReasonCombinationsAreRejected(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new DimensionOutcome(
            'country',
            true,
            DimensionReasonEnum::DENIED_BY_RULE,
            new RuleReferenceCollection(),
        );
    }

    #[Test]
    public function ruleReferencesExposeCanonicalFieldsDirectly(): void
    {
        $reference = new RuleReference(
            'product',
            '150',
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        );

        self::assertSame('product', $reference->subjectType);
        self::assertSame('150', $reference->subjectId);
        self::assertSame('country', $reference->dimensionKey);
        self::assertSame('EG', $reference->dimensionValue);
        self::assertSame(RuleEffectEnum::ALLOW, $reference->effect);
        self::assertSame([
            'subjectType' => 'product',
            'subjectId' => '150',
            'dimensionKey' => 'country',
            'dimensionValue' => 'EG',
            'effect' => 'allow',
        ], $reference->jsonSerialize());
    }

    #[Test]
    public function matchedRuleReferencesMustUseOneSubjectAndDimension(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new RuleReferenceCollection(
            $this->reference('EG', RuleEffectEnum::ALLOW),
            RuleReference::fromRule(
                Rule::active(new Subject('customer', '150'), 'country', 'SA', RuleEffectEnum::ALLOW),
            ),
        );
    }

    #[Test]
    public function duplicateDimensionOutcomesAreRejectedAsTypedInputErrors(): void
    {
        $outcome = new DimensionOutcome(
            'country',
            true,
            DimensionReasonEnum::PASSED_DENY_LIST,
            new RuleReferenceCollection(),
        );

        $this->expectException(InvalidEligibilityInputException::class);

        new DimensionOutcomeCollection($outcome, $outcome);
    }

    #[Test]
    public function duplicateMatchedRuleReferencesAreRejectedAsTypedInputErrors(): void
    {
        $reference = $this->reference('EG', RuleEffectEnum::ALLOW);

        $this->expectException(InvalidEligibilityInputException::class);

        new RuleReferenceCollection($reference, $reference);
    }

    #[Test]
    public function matchedRuleReferencesMustBelongToTheOutcomeDimension(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new DimensionOutcome(
            'country',
            true,
            DimensionReasonEnum::PASSED_ALLOW_LIST,
            new RuleReferenceCollection($this->reference('EG', RuleEffectEnum::ALLOW, 'customer_type')),
        );
    }

    #[Test]
    public function thePackageMarkerExtendsThrowable(): void
    {
        $marker = new \ReflectionClass(EligibilityExceptionInterface::class);

        self::assertTrue($marker->implementsInterface(\Throwable::class));
    }

    #[Test]
    public function typedInputErrorsUseThePackageMarkerAndSharedValidationHierarchy(): void
    {
        $exception = new InvalidEligibilityInputException('invalid input');

        self::assertInstanceOf(EligibilityExceptionInterface::class, $exception);
        self::assertInstanceOf(
            \Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException::class,
            $exception,
        );
        self::assertInstanceOf(\Throwable::class, $exception);
    }

    private function reference(
        string $dimensionValue,
        RuleEffectEnum $effect,
        string $dimensionKey = 'country',
    ): RuleReference
    {
        return RuleReference::fromRule(
            Rule::active(new Subject('product', '150'), $dimensionKey, $dimensionValue, $effect),
        );
    }
}
