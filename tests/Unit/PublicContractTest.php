<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\ValueObject\DesiredRule;
use Maatify\Eligibility\Management\ValueObject\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\Criteria\RuleLifecycleSummaryCriteria;
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyDTO;
use Maatify\Eligibility\Management\DTO\RuleLifecycleSummaryDTO;
use Maatify\Eligibility\Evaluation\DTO\SubjectDecisionCollectionDTO;
use Maatify\Eligibility\Evaluation\DTO\SubjectDecisionDTO;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Management\Service\EligibilityManagementServiceInterface;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Factory\Pdo\PdoEligibilityRuntimeFactory;
use Maatify\Eligibility\Evaluation\ValueObject\EligibilityDecision;
use Maatify\Eligibility\Exception\EligibilityExceptionInterface;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\RuleConcurrencyConflictException;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Exception\InvalidPersistedRuleStateException;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\Evaluation\Repository\ActiveRuleReaderInterface;
use Maatify\Eligibility\Management\Repository\Pdo\PdoRuleCommandRepository;
use Maatify\Eligibility\Management\Repository\RuleCommandRepositoryInterface;
use Maatify\Eligibility\Management\Repository\RuleManagementQueryInterface;
use Maatify\Eligibility\Management\Repository\RuleMutationSupportInterface;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\NonStrictConsumer;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\ValueObject\SubjectCollection;
use Maatify\Exceptions\Contracts\ApiAwareExceptionInterface;
use Maatify\Exceptions\Exception\Conflict\ConflictMaatifyException;
use Maatify\Exceptions\Exception\MaatifyException;
use Maatify\Exceptions\Exception\NotFound\NotFoundMaatifyException;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use Maatify\Persistence\Pdo\Transaction\PdoTransactionRunner;
use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;
use Maatify\Persistence\Pdo\Transaction\TransactionRunnerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use PDO;

final class PublicContractTest extends TestCase
{
    #[Test]
    public function pdoRuntimeFactoryExposesOnlyTheLockedPublicConstructionContract(): void
    {
        $reflection = new ReflectionClass(PdoEligibilityRuntimeFactory::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame('Maatify\\Eligibility\\Factory\\Pdo', $reflection->getNamespaceName());
        self::assertSame(['pdo'], array_map(
            static fn(\ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties(),
        ));
        self::assertSame([
            '__construct',
            'createManagementService',
            'createEvaluationService',
        ], array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        ));

        $constructor = $reflection->getMethod('__construct');
        self::assertSame(PDO::class, self::parameterTypeName($constructor->getParameters()[0]));
        self::assertSame(
            EligibilityManagementServiceInterface::class,
            self::namedReturnTypeName($reflection->getMethod('createManagementService')),
        );
        self::assertSame(
            EligibilityEvaluationServiceInterface::class,
            self::namedReturnTypeName($reflection->getMethod('createEvaluationService')),
        );
    }

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

        $results = new SubjectDecisionCollectionDTO(
            new SubjectDecisionDTO($firstSubject, $firstDecision),
            new SubjectDecisionDTO($secondSubject, $secondDecision),
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

        new SubjectDecisionCollectionDTO(
            new SubjectDecisionDTO($subject, $decision),
            new SubjectDecisionDTO(new Subject('product', '150'), $decision),
        );
    }

    #[Test]
    public function activeDimensionKeyDtoIsACanonicalString(): void
    {
        $key = new ActiveDimensionKeyDTO('country');

        self::assertSame('country', $key->dimensionKey);
        self::assertSame(['dimensionKey' => 'country'], $key->jsonSerialize());
    }

    #[Test]
    public function activeDimensionKeyDtoRejectsNonStrings(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new ActiveDimensionKeyDTO(1);
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
    public function criteriaRepresentSubjectDimensionLifecycleAndEffect(): void
    {
        $criteria = new RuleCriteria(
            new Subject('product', '150'),
            'country',
            RuleLifecycleEnum::INACTIVE,
            RuleEffectEnum::DENY,
        );

        self::assertSame('country', $criteria->dimensionKey);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $criteria->lifecycle);
        self::assertSame(RuleEffectEnum::DENY, $criteria->effect);
        self::assertNull((new RuleCriteria(new Subject('product', '1')))->effect);
        self::assertFalse((new ReflectionClass(RuleCriteria::class))->hasConstant('DEFAULT_MAX_RESULTS'));
        self::assertFalse((new ReflectionClass(RuleCriteria::class))->hasConstant('MAX_MAX_RESULTS'));
    }

    #[Test]
    public function criteriaRejectNonStringDimensionKeys(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new RuleCriteria(new Subject('product', '150'), 1);
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

        NonStrictConsumer::ruleCriteria($subject, 1);
    }

    #[Test]
    public function activeDimensionQueryUsesATypedSubjectContract(): void
    {
        $query = new ActiveDimensionKeysCriteria(new Subject('product', '150'));

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
    public function repositoryAndServicesExposeSeparatedTypedBoundaries(): void
    {
        $commandRepository = new ReflectionClass(RuleCommandRepositoryInterface::class);
        $managementQuery = new ReflectionClass(RuleManagementQueryInterface::class);
        $activeRuleReader = new ReflectionClass(ActiveRuleReaderInterface::class);
        $mutationSupport = new ReflectionClass(RuleMutationSupportInterface::class);
        $commandRepositoryImplementation = new ReflectionClass(PdoRuleCommandRepository::class);
        $evaluationService = new ReflectionClass(EligibilityEvaluationServiceInterface::class);
        $managementService = new ReflectionClass(EligibilityManagementServiceInterface::class);
        $evaluationServiceImplementation = new ReflectionClass(EligibilityEvaluationService::class);
        $managementServiceImplementation = new ReflectionClass(EligibilityManagementService::class);

        self::assertTrue($commandRepository->isInterface());
        self::assertTrue($managementQuery->isInterface());
        self::assertTrue($activeRuleReader->isInterface());
        self::assertTrue($mutationSupport->isInterface());
        self::assertTrue($evaluationService->isInterface());
        self::assertTrue($managementService->isInterface());
        self::assertTrue($commandRepositoryImplementation->implementsInterface(RuleCommandRepositoryInterface::class));
        self::assertTrue($commandRepositoryImplementation->implementsInterface(RuleMutationSupportInterface::class));
        self::assertFalse($commandRepositoryImplementation->implementsInterface(SavepointTransactionRunnerInterface::class));

        $commandMethods = [
            'create' => Rule::class,
            'updateEffect' => 'bool',
            'deactivate' => 'bool',
            'reactivate' => 'bool',
            'cleanupSubject' => 'void',
        ];

        foreach ($commandMethods as $methodName => $returnType) {
            self::assertSame(
                $returnType,
                self::namedReturnTypeName($commandRepository->getMethod($methodName)),
            );
        }

        foreach (
            [
                'findByIdentity' => Rule::class,
                'findByCriteria' => PageResult::class,
                'findActiveDimensionKeys' => PageResult::class,
                'summarizeLifecycle' => RuleLifecycleSummaryDTO::class,
            ] as $methodName => $returnType
        ) {
            self::assertSame(
                $returnType,
                self::namedReturnTypeName($managementQuery->getMethod($methodName)),
            );
        }
        self::assertCount(2, $managementQuery->getMethod('findByCriteria')->getParameters());
        self::assertCount(2, $managementQuery->getMethod('findActiveDimensionKeys')->getParameters());

        self::assertSame(
            RuleCollection::class,
            self::namedReturnTypeName($activeRuleReader->getMethod('findActiveForSubjects')),
        );

        foreach (['lockSubjectForMutation', 'findAllForSubjectDimension', 'deleteSubjectCoordination'] as $methodName) {
            self::assertTrue($mutationSupport->hasMethod($methodName));
        }

        self::assertFalse($commandRepository->hasMethod('replaceDimensionRules'));
        foreach (
            ['inTransaction', 'beginTransaction', 'commit', 'rollBack', 'createOperationSavepoint',
                'rollbackToOperationSavepoint', 'releaseOperationSavepoint'] as $methodName
        ) {
            self::assertFalse($commandRepositoryImplementation->hasMethod($methodName));
        }
        self::assertTrue($managementService->hasMethod('replaceDimensionRules'));
        self::assertSame(
            Rule::class,
            self::namedReturnTypeName($managementService->getMethod('createRule')),
        );
        self::assertSame(
            'void',
            self::namedReturnTypeName($managementService->getMethod('replaceDimensionRules')),
        );
        self::assertSame(
            PageResult::class,
            self::namedReturnTypeName($managementService->getMethod('inspectRules')),
        );
        self::assertSame(
            PageResult::class,
            self::namedReturnTypeName($managementService->getMethod('inspectActiveDimensionKeys')),
        );
        self::assertSame(
            RuleLifecycleSummaryDTO::class,
            self::namedReturnTypeName($managementService->getMethod('inspectRuleLifecycleSummary')),
        );
        self::assertSame(
            SubjectDecisionCollectionDTO::class,
            self::namedReturnTypeName($evaluationService->getMethod('decideMany')),
        );

        $managementConstructor = $managementServiceImplementation->getConstructor();
        self::assertNotNull($managementConstructor);
        self::assertSame(4, count($managementConstructor->getParameters()));
        self::assertSame(
            RuleCommandRepositoryInterface::class,
            self::parameterTypeName($managementConstructor->getParameters()[0]),
        );
        self::assertSame(
            RuleManagementQueryInterface::class,
            self::parameterTypeName($managementConstructor->getParameters()[1]),
        );
        self::assertSame(
            RuleMutationSupportInterface::class,
            self::parameterTypeName($managementConstructor->getParameters()[2]),
        );
        self::assertSame(
            SavepointTransactionRunnerInterface::class,
            self::parameterTypeName($managementConstructor->getParameters()[3]),
        );

        $evaluationConstructor = $evaluationServiceImplementation->getConstructor();
        self::assertNotNull($evaluationConstructor);
        self::assertSame(1, count($evaluationConstructor->getParameters()));
        self::assertSame(
            ActiveRuleReaderInterface::class,
            self::parameterTypeName($evaluationConstructor->getParameters()[0]),
        );
    }

    #[Test]
    public function persistenceSavepointApiMatchesReleasedContract(): void
    {
        self::assertTrue(interface_exists(TransactionRunnerInterface::class));
        self::assertTrue(interface_exists(SavepointTransactionRunnerInterface::class));
        self::assertTrue(class_exists(PdoTransactionRunner::class));
        self::assertTrue(class_exists(PdoSavepointTransactionRunner::class));

        $transactionRunnerInterface = new ReflectionClass(TransactionRunnerInterface::class);
        $savepointRunnerInterface = new ReflectionClass(SavepointTransactionRunnerInterface::class);

        self::assertTrue($transactionRunnerInterface->isInterface());
        self::assertTrue($savepointRunnerInterface->isInterface());
        self::assertTrue(
            $savepointRunnerInterface->implementsInterface(TransactionRunnerInterface::class),
        );

        $run = $transactionRunnerInterface->getMethod('run');

        self::assertSame('mixed', self::namedReturnTypeName($run));
        self::assertCount(1, $run->getParameters());
        self::assertSame('callable', self::parameterTypeName($run->getParameters()[0]));
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
            ActiveDimensionKeysCriteria::class,
            ActiveDimensionKeyDTO::class,
            RuleLifecycleSummaryCriteria::class,
            RuleLifecycleSummaryDTO::class,
            SubjectDecisionDTO::class,
            SubjectDecisionCollectionDTO::class,
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

    private static function parameterTypeName(\ReflectionParameter $parameter): string
    {
        $parameterType = $parameter->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $parameterType);

        return $parameterType->getName();
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

    #[Test]
    public function persistedStateExceptionUsesTheSystemHierarchyAndRetainsPrevious(): void
    {
        $causeFromInput = new InvalidEligibilityInputException('malformed persisted component');
        $wrappingInput = new InvalidPersistedRuleStateException('invalid persisted component', $causeFromInput);

        self::assertInstanceOf(EligibilityExceptionInterface::class, $wrappingInput);
        self::assertInstanceOf(SystemMaatifyException::class, $wrappingInput);
        self::assertInstanceOf(ApiAwareExceptionInterface::class, $wrappingInput);
        self::assertSame($causeFromInput, $wrappingInput->getPrevious());
        self::assertSame(500, $wrappingInput->getHttpStatus());
        self::assertFalse($wrappingInput->isSafe());

        $causeFromValueError = new \ValueError('not a backed enum case');
        $wrappingValueError = new InvalidPersistedRuleStateException('invalid persisted effect', $causeFromValueError);
        self::assertSame($causeFromValueError, $wrappingValueError->getPrevious());
    }

    #[Test]
    public function everyEligibilityExceptionImplementsTheMarkerAndAMaatifyHierarchy(): void
    {
        $identity = new RuleIdentity('product', '150', 'country', 'EG');
        $exceptions = [
            new InvalidEligibilityInputException('invalid'),
            new RuleNotFoundException($identity),
            new RuleIdentityConflictException($identity),
            new RuleConcurrencyConflictException(),
            new InvalidPersistedRuleStateException('invalid persisted state'),
        ];

        foreach ($exceptions as $exception) {
            self::assertInstanceOf(EligibilityExceptionInterface::class, $exception);
            // ApiAwareExceptionInterface alone would only prove interface
            // compatibility; MaatifyException proves the exception is
            // actually built on the shared maatify/exceptions class
            // hierarchy, not merely a same-shaped independent implementation.
            self::assertInstanceOf(MaatifyException::class, $exception);
            self::assertInstanceOf(ApiAwareExceptionInterface::class, $exception);
        }
    }
}
