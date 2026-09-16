<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Application\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Application\Command\DesiredRule;
use Maatify\Eligibility\Application\Command\DesiredRuleCollection;
use Maatify\Eligibility\Application\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Application\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Application\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Application\Result\SubjectDecisionCollection;
use Maatify\Eligibility\Application\Result\SubjectDecisionResult;
use Maatify\Eligibility\Application\Service\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Application\Service\EligibilityManagementServiceInterface;
use Maatify\Eligibility\Decision\EligibilityDecision;
use Maatify\Eligibility\Exception\EligibilityExceptionInterface;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\RuleConcurrencyConflictException;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\Rule\Repository\RuleRepositoryInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\NonStrictConsumer;
use Maatify\Eligibility\Value\Subject;
use Maatify\Eligibility\Value\SubjectCollection;
use Maatify\Exceptions\Contracts\ApiAwareExceptionInterface;
use Maatify\Exceptions\Exception\Conflict\ConflictMaatifyException;
use Maatify\Exceptions\Exception\NotFound\NotFoundMaatifyException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class PublicContractTest extends TestCase
{
    #[Test]
    public function subjectBatchCollectionAcceptsEmptyInputAndPreservesOrder(): void
    {
        $first = new Subject('product', '150');
        $second = new Subject('category', '25');
        $subjects = new SubjectCollection($second, $first);

        self::assertSame([], (new SubjectCollection())->items());
        self::assertSame([$second, $first], $subjects->items());
    }

    #[Test]
    public function subjectBatchCollectionRejectsDuplicateNaturalIdentities(): void
    {
        $subject = new Subject('product', '150');

        $this->expectException(InvalidEligibilityInputException::class);

        new SubjectCollection($subject, new Subject('product', '150'));
    }

    #[Test]
    public function subjectDecisionResultsKeepTypedAssociationAndInputOrder(): void
    {
        $firstSubject = new Subject('category', '25');
        $secondSubject = new Subject('product', '150');
        $firstDecision = EligibilityDecision::unrestricted();
        $secondDecision = EligibilityDecision::unrestricted();

        $results = new SubjectDecisionCollection(
            new SubjectDecisionResult($firstSubject, $firstDecision),
            new SubjectDecisionResult($secondSubject, $secondDecision),
        );

        self::assertSame($firstSubject, $results->items()[0]->subject);
        self::assertSame($firstDecision, $results->items()[0]->decision);
        self::assertSame($secondSubject, $results->items()[1]->subject);
        self::assertSame($secondDecision, $results->items()[1]->decision);
    }

    #[Test]
    public function subjectDecisionResultsRejectDuplicateSubjects(): void
    {
        $subject = new Subject('product', '150');
        $decision = EligibilityDecision::unrestricted();

        $this->expectException(InvalidEligibilityInputException::class);

        new SubjectDecisionCollection(
            new SubjectDecisionResult($subject, $decision),
            new SubjectDecisionResult(new Subject('product', '150'), $decision),
        );
    }

    #[Test]
    public function activeDimensionKeysAreCanonicalStringsInBytewiseOrder(): void
    {
        $keys = new ActiveDimensionKeyCollection('customer_type', 'country', 'customer_segment');

        self::assertSame(['country', 'customer_segment', 'customer_type'], $keys->items());
        self::assertTrue($keys->contains('country'));
    }

    #[Test]
    public function activeDimensionKeysRejectDuplicateKeys(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new ActiveDimensionKeyCollection('country', 'country');
    }

    #[Test]
    public function activeDimensionKeysRejectNonStrings(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new ActiveDimensionKeyCollection('country', 1);
    }

    #[Test]
    public function desiredReplacementSetMayBeEmptyAndOrdersTypedEffects(): void
    {
        $empty = new DesiredRuleCollection();
        $desired = new DesiredRuleCollection(
            new DesiredRule('SA', RuleEffectEnum::DENY),
            new DesiredRule('EG', RuleEffectEnum::ALLOW),
        );

        self::assertCount(0, $empty);
        self::assertSame('EG', $desired->items()[0]->dimensionValue);
        self::assertSame(RuleEffectEnum::ALLOW, $desired->items()[0]->effect);
    }

    #[Test]
    public function desiredReplacementSetRejectsDuplicateNaturalValues(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new DesiredRuleCollection(
            new DesiredRule('EG', RuleEffectEnum::ALLOW),
            new DesiredRule('EG', RuleEffectEnum::DENY),
        );
    }

    #[Test]
    public function commandsRepresentTypedMutationIntentWithoutInitialLifecycle(): void
    {
        $subject = new Subject('product', '150');
        $identity = new RuleIdentity('product', '150', 'country', 'EG');
        $create = new CreateRuleCommand($subject, 'country', 'EG', RuleEffectEnum::ALLOW);
        $replacement = new ReplaceDimensionRulesCommand($subject, 'country', new DesiredRuleCollection());

        self::assertSame('country', $create->dimensionKey);
        self::assertSame('EG', $create->dimensionValue);
        self::assertSame([], $replacement->desiredRules->items());
        self::assertSame($identity->dimensionKey, $replacement->dimensionKey);
        self::assertFalse((new ReflectionClass(CreateRuleCommand::class))->hasProperty('lifecycle'));

        $commands = [
            new UpdateRuleEffectCommand($identity, RuleEffectEnum::DENY),
            new DeactivateRuleCommand($identity),
            new ReactivateRuleCommand($identity),
            new CleanupSubjectCommand($subject),
        ];

        self::assertCount(4, $commands);
    }

    #[Test]
    public function criteriaRepresentSubjectDimensionLifecycleAndBoundedRead(): void
    {
        $criteria = new RuleCriteria(
            new Subject('product', '150'),
            'country',
            RuleLifecycleEnum::INACTIVE,
            25,
        );

        self::assertSame('country', $criteria->dimensionKey);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $criteria->lifecycle);
        self::assertSame(25, $criteria->maxResults);
        self::assertSame(RuleCriteria::DEFAULT_MAX_RESULTS, (new RuleCriteria(new Subject('product', '1')))->maxResults);
    }

    #[Test]
    public function criteriaRejectNonStringDimensionKeys(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new RuleCriteria(new Subject('product', '150'), 1);
    }

    #[Test]
    public function criteriaRejectZeroMaxResults(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new RuleCriteria(new Subject('product', '150'), null, null, 0);
    }

    #[Test]
    public function criteriaRejectMaxResultsAboveBound(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new RuleCriteria(new Subject('product', '150'), null, null, RuleCriteria::MAX_MAX_RESULTS + 1);
    }

    #[Test]
    public function nonStrictCreateCommandBoundaryRejectsCoercion(): void
    {
        $subject = new Subject('product', '150');

        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::createRuleCommand($subject, 1, 'EG');
    }

    #[Test]
    public function nonStrictCriteriaBoundaryRejectsCoercion(): void
    {
        $subject = new Subject('product', '150');

        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::ruleCriteria($subject, 'country', '25');
    }

    #[Test]
    public function activeDimensionQueryUsesATypedSubjectContract(): void
    {
        $query = new ActiveDimensionKeysQuery(new Subject('product', '150'));

        self::assertSame('product', $query->subject->subjectType);
    }

    #[Test]
    public function managementResultsReuseRuleAndExposeLifecycle(): void
    {
        $inactive = Rule::active(
            new Subject('product', '150'),
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        )->withLifecycle(RuleLifecycleEnum::INACTIVE);
        $results = new RuleCollection($inactive);

        self::assertSame(RuleLifecycleEnum::INACTIVE, $results->items()[0]->lifecycle);
    }

    #[Test]
    public function repositoryAndServicesExposeReplaceableTypedBoundaries(): void
    {
        self::assertTrue((new ReflectionClass(RuleRepositoryInterface::class))->isInterface());
        self::assertTrue((new ReflectionClass(EligibilityEvaluationServiceInterface::class))->isInterface());
        self::assertTrue((new ReflectionClass(EligibilityManagementServiceInterface::class))->isInterface());

        $repositoryMethods = [
            'create' => 'int',
            'findByIdentity' => Rule::class,
            'findByCriteria' => RuleCollection::class,
            'findActiveForSubjects' => RuleCollection::class,
            'findActiveDimensionKeys' => ActiveDimensionKeyCollection::class,
            'updateEffect' => 'bool',
            'deactivate' => 'bool',
            'reactivate' => 'bool',
            'replaceDimensionRules' => 'void',
            'cleanupSubject' => 'void',
        ];

        foreach ($repositoryMethods as $methodName => $returnType) {
            self::assertSame(
                $returnType,
                self::namedReturnTypeName(
                    (new ReflectionClass(RuleRepositoryInterface::class))->getMethod($methodName),
                ),
            );
        }

        self::assertSame(
            SubjectDecisionCollection::class,
            self::namedReturnTypeName(
                (new ReflectionClass(EligibilityEvaluationServiceInterface::class))->getMethod('decideMany'),
            ),
        );
    }

    #[Test]
    public function publicB2ModelsDoNotExposeAssociativeArrayState(): void
    {
        $classes = [
            CreateRuleCommand::class,
            UpdateRuleEffectCommand::class,
            DeactivateRuleCommand::class,
            ReactivateRuleCommand::class,
            ReplaceDimensionRulesCommand::class,
            CleanupSubjectCommand::class,
            DesiredRule::class,
            RuleCriteria::class,
            ActiveDimensionKeysQuery::class,
            ActiveDimensionKeyCollection::class,
            SubjectDecisionResult::class,
            SubjectDecisionCollection::class,
            SubjectCollection::class,
        ];

        foreach ($classes as $className) {
            foreach ((new ReflectionClass($className))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $propertyType = $property->getType();
                self::assertInstanceOf(ReflectionNamedType::class, $propertyType);
                self::assertNotSame('array', $propertyType->getName(), $className . ' exposes array state.');
            }
        }
    }

    private static function namedReturnTypeName(ReflectionMethod $method): string
    {
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);

        return $returnType->getName();
    }

    #[Test]
    public function semanticExceptionsUseEligibilityAndSharedHierarchies(): void
    {
        $identity = new RuleIdentity('product', '150', 'country', 'EG');
        $previous = new \RuntimeException('storage conflict');
        $notFound = new RuleNotFoundException($identity);
        $identityConflict = new RuleIdentityConflictException($identity, $previous);
        $concurrency = new RuleConcurrencyConflictException(previous: $previous);

        self::assertInstanceOf(EligibilityExceptionInterface::class, $notFound);
        self::assertInstanceOf(NotFoundMaatifyException::class, $notFound);
        self::assertInstanceOf(ApiAwareExceptionInterface::class, $notFound);
        self::assertSame($identity, $notFound->identity());
        self::assertInstanceOf(EligibilityExceptionInterface::class, $identityConflict);
        self::assertInstanceOf(ConflictMaatifyException::class, $identityConflict);
        self::assertSame($previous, $identityConflict->getPrevious());
        self::assertInstanceOf(EligibilityExceptionInterface::class, $concurrency);
        self::assertInstanceOf(ConflictMaatifyException::class, $concurrency);
        self::assertSame($previous, $concurrency->getPrevious());
    }
}
