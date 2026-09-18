<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Evaluation\Decision\DimensionOutcome;
use Maatify\Eligibility\Evaluation\Decision\DimensionReasonEnum;
use Maatify\Eligibility\Evaluation\Decision\RuleReference;
use Maatify\Eligibility\Evaluation\Decision\RuleReferenceCollection;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Tests\Support\NonStrictConsumer;
use Maatify\Eligibility\Common\Validation\CanonicalString;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Evaluation\Value\ContextDimension;
use Maatify\Eligibility\Evaluation\Value\ContextValue;
use Maatify\Eligibility\Evaluation\Value\ContextValueCollection;
use Maatify\Eligibility\Common\Value\Subject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalBoundsTest extends TestCase
{
    #[Test]
    public function canonicalBoundsAreOneSourceOfTruthAndExactLimitsAreAccepted(): void
    {
        self::assertSame(64, CanonicalString::SUBJECT_TYPE_MAX_BYTES);
        self::assertSame(191, CanonicalString::SUBJECT_ID_MAX_BYTES);
        self::assertSame(64, CanonicalString::DIMENSION_KEY_MAX_BYTES);
        self::assertSame(255, CanonicalString::DIMENSION_VALUE_MAX_BYTES);

        $subjectType = str_repeat('s', CanonicalString::SUBJECT_TYPE_MAX_BYTES);
        $subjectId = str_repeat('i', CanonicalString::SUBJECT_ID_MAX_BYTES);
        $dimensionKey = str_repeat('k', CanonicalString::DIMENSION_KEY_MAX_BYTES);
        $dimensionValue = str_repeat('v', CanonicalString::DIMENSION_VALUE_MAX_BYTES);
        $subject = new Subject($subjectType, $subjectId);
        $identity = new RuleIdentity($subjectType, $subjectId, $dimensionKey, $dimensionValue);
        $rule = new Rule($subject, $dimensionKey, $dimensionValue, RuleEffectEnum::ALLOW);
        $create = new CreateRuleCommand($subject, $dimensionKey, $dimensionValue, RuleEffectEnum::ALLOW);
        $desired = new DesiredRule($dimensionValue, RuleEffectEnum::ALLOW);
        $replacement = new ReplaceDimensionRulesCommand(
            $subject,
            $dimensionKey,
            new DesiredRuleCollection($desired),
        );
        $criteria = new RuleCriteria($subject, $dimensionKey);
        $contextValues = new ContextValueCollection(new ContextValue($dimensionValue));
        $context = new Context(new ContextDimension($dimensionKey, $contextValues));
        $reference = new RuleReference($subjectType, $subjectId, $dimensionKey, $dimensionValue, RuleEffectEnum::ALLOW);
        $outcome = new DimensionOutcome(
            $dimensionKey,
            true,
            DimensionReasonEnum::PASSED_ALLOW_LIST,
            new RuleReferenceCollection($reference),
        );
        $activeKeys = new ActiveDimensionKeyCollection($dimensionKey);

        self::assertSame($subjectType, $identity->subjectType);
        self::assertSame($subjectId, $rule->subject->subjectId);
        self::assertSame($dimensionKey, $create->dimensionKey);
        self::assertSame($dimensionValue, $desired->dimensionValue);
        self::assertSame($dimensionKey, $replacement->dimensionKey);
        self::assertSame($dimensionKey, $criteria->dimensionKey);
        self::assertSame($dimensionValue, $contextValues->values()[0]);
        self::assertSame($dimensionKey, $context->getDimension($dimensionKey)?->dimensionKey);
        self::assertSame($dimensionKey, $outcome->dimensionKey);
        self::assertTrue($activeKeys->contains($dimensionKey));
    }

    #[Test]
    #[DataProvider('overLimitComponents')]
    public function everyCanonicalComponentRejectsOneByteOverItsLimit(string $component): void
    {
        $this->assertInvalid(function () use ($component): void {
            match ($component) {
                'subject_type' => new Subject(
                    str_repeat('s', CanonicalString::SUBJECT_TYPE_MAX_BYTES + 1),
                    'id',
                ),
                'subject_id' => new Subject(
                    'type',
                    str_repeat('i', CanonicalString::SUBJECT_ID_MAX_BYTES + 1),
                ),
                'dimension_key' => new ContextDimension(
                    str_repeat('k', CanonicalString::DIMENSION_KEY_MAX_BYTES + 1),
                    new ContextValueCollection(new ContextValue('value')),
                ),
                'dimension_value' => new ContextValue(
                    str_repeat('v', CanonicalString::DIMENSION_VALUE_MAX_BYTES + 1),
                ),
                default => throw new \LogicException('Unknown canonical component test case.'),
            };
        });
    }

    /** @return iterable<string, array{string}> */
    public static function overLimitComponents(): iterable
    {
        yield 'subject_type' => ['subject_type'];
        yield 'subject_id' => ['subject_id'];
        yield 'dimension_key' => ['dimension_key'];
        yield 'dimension_value' => ['dimension_value'];
    }

    #[Test]
    public function multibyteUtf8IsMeasuredByBytesRatherThanCharacters(): void
    {
        $exactDimensionKey = str_repeat('é', 32);
        self::assertSame(64, strlen($exactDimensionKey));
        self::assertSame($exactDimensionKey, new ContextDimension(
            $exactDimensionKey,
            new ContextValueCollection(new ContextValue('value')),
        )->dimensionKey);

        $exactDimensionValue = str_repeat('é', 127) . 'x';
        self::assertSame(255, strlen($exactDimensionValue));
        self::assertSame($exactDimensionValue, new ContextValue($exactDimensionValue)->value);

        $this->assertInvalid(static fn (): ContextDimension => new ContextDimension(
            str_repeat('é', 33),
            new ContextValueCollection(new ContextValue('value')),
        ));
        $this->assertInvalid(static fn (): ContextValue => new ContextValue(str_repeat('é', 128)));
    }

    #[Test]
    public function nonStrictConsumersCannotBypassCanonicalBoundsOrScalarRules(): void
    {
        $subject = new Subject('product', '150');

        $this->assertInvalid(static fn (): CreateRuleCommand => NonStrictConsumer::createRuleCommand(
            $subject,
            'country',
            str_repeat('v', CanonicalString::DIMENSION_VALUE_MAX_BYTES + 1),
        ));
        $this->assertInvalid(static fn (): RuleCriteria => NonStrictConsumer::ruleCriteria(
            $subject,
            str_repeat('k', CanonicalString::DIMENSION_KEY_MAX_BYTES + 1),
            25,
        ));
        $this->assertInvalid(static fn (): CreateRuleCommand => NonStrictConsumer::createRuleCommand(
            $subject,
            1,
            'EG',
        ));
    }

    #[Test]
    public function contextRawLookupsAndCollectionMembershipUseBoundedComponents(): void
    {
        $context = new Context(ContextDimension::fromStrings('country', 'EG'));
        $values = new ContextValueCollection(new ContextValue('EG'));
        $keys = new ActiveDimensionKeyCollection('country');

        $this->assertInvalid(static fn (): ?ContextDimension => $context->getDimension(
            str_repeat('k', CanonicalString::DIMENSION_KEY_MAX_BYTES + 1),
        ));
        $this->assertInvalid(static fn (): bool => $context->hasDimension(
            str_repeat('k', CanonicalString::DIMENSION_KEY_MAX_BYTES + 1),
        ));
        $this->assertInvalid(static fn (): bool => $values->contains(
            str_repeat('v', CanonicalString::DIMENSION_VALUE_MAX_BYTES + 1),
        ));
        $this->assertInvalid(static fn (): bool => $keys->contains(
            str_repeat('k', CanonicalString::DIMENSION_KEY_MAX_BYTES + 1),
        ));
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected InvalidEligibilityInputException.');
        } catch (InvalidEligibilityInputException) {
            self::addToAssertionCount(1);
        }
    }
}
