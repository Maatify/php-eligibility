<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use InvalidArgumentException;
use Maatify\Eligibility\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Decision\DimensionOutcome;
use Maatify\Eligibility\Decision\DimensionOutcomeCollection;
use Maatify\Eligibility\Decision\DimensionReasonEnum;
use Maatify\Eligibility\Decision\EligibilityDecision;
use Maatify\Eligibility\Decision\RuleReference;
use Maatify\Eligibility\Decision\RuleReferenceCollection;
use Maatify\Eligibility\Exception\EligibilityExceptionInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Value\Subject;
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

        $this->expectException(InvalidArgumentException::class);

        new EligibilityDecision(
            true,
            DecisionReasonEnum::UNRESTRICTED,
            new DimensionOutcomeCollection($outcome),
        );
    }

    #[Test]
    public function invalidDimensionReasonCombinationsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DimensionOutcome(
            'country',
            true,
            DimensionReasonEnum::DENIED_BY_RULE,
            new RuleReferenceCollection(),
        );
    }

    #[Test]
    public function matchedRuleReferencesMustBelongToTheOutcomeDimension(): void
    {
        $this->expectException(InvalidArgumentException::class);

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
