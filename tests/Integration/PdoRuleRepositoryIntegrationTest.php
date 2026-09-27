<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Integration;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\Criteria\RuleLifecycleSummaryCriteria;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Evaluation\Enum\DecisionReasonEnum;
use Maatify\Eligibility\Exception\EligibilityExceptionInterface;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\InvalidPersistedRuleStateException;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Evaluation\Repository\Pdo\PdoActiveRuleReader;
use Maatify\Eligibility\Management\Repository\Pdo\PdoRuleCommandRepository;
use Maatify\Eligibility\Management\Repository\Pdo\PdoRuleManagementQuery;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\Evaluation\ValueObject\Context;
use Maatify\Eligibility\Evaluation\ValueObject\ContextDimension;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\ValueObject\SubjectCollection;
use Maatify\Exceptions\Exception\MaatifyException;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PdoRuleRepositoryIntegrationTest extends TestCase
{
    private PDO $pdo;

    private PdoRuleCommandRepository $repository;

    private PdoRuleManagementQuery $managementQuery;

    private PdoActiveRuleReader $activeRuleReader;

    protected function setUp(): void
    {
        $this->pdo = IntegrationDatabase::connect();
        IntegrationDatabase::applySchema($this->pdo);
        IntegrationDatabase::clearRules($this->pdo);
        $this->repository = new PdoRuleCommandRepository($this->pdo);
        $this->managementQuery = new PdoRuleManagementQuery($this->pdo);
        $this->activeRuleReader = new PdoActiveRuleReader($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        IntegrationDatabase::clearRules($this->pdo);
        self::assertFalse($this->pdo->inTransaction());
    }

    #[Test]
    public function schemaAppliesFreshAndReappliesWithoutLosingValidData(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS `maa_eligibility_rules`');
        IntegrationDatabase::applySchema($this->pdo);

        $created = $this->repository->create($this->command('product', '150', 'country', 'EG'));
        IntegrationDatabase::applySchema($this->pdo);

        $found = $this->managementQuery->findByIdentity($created->naturalIdentity());
        self::assertNotNull($found);
        self::assertSame('EG', $found->dimensionValue);
        self::assertSame(RuleLifecycleEnum::ACTIVE, $found->lifecycle);

        $columnStatement = $this->pdo->query('SHOW COLUMNS FROM `maa_eligibility_rules`');
        if ($columnStatement === false) {
            self::fail('Could not inspect the Eligibility schema columns.');
        }
        /** @var list<array<string, mixed>> $columns */
        $columns = $columnStatement->fetchAll(PDO::FETCH_ASSOC);
        $columnTypes = [];
        foreach ($columns as $column) {
            $field = $column['Field'] ?? null;
            $type = $column['Type'] ?? null;
            if (is_string($field) && is_string($type)) {
                $columnTypes[$field] = strtolower($type);
            }
        }

        self::assertSame('bigint unsigned', $columnTypes['id'] ?? null);
        self::assertSame('varbinary(64)', $columnTypes['subject_type'] ?? null);
        self::assertSame('varbinary(191)', $columnTypes['subject_id'] ?? null);
        self::assertSame('varbinary(64)', $columnTypes['dimension_key'] ?? null);
        self::assertSame('varbinary(255)', $columnTypes['dimension_value'] ?? null);
        self::assertSame('varbinary(5)', $columnTypes['effect'] ?? null);
        self::assertSame('varbinary(8)', $columnTypes['lifecycle'] ?? null);

        $indexStatement = $this->pdo->query('SHOW INDEX FROM `maa_eligibility_rules`');
        if ($indexStatement === false) {
            self::fail('Could not inspect the Eligibility schema indexes.');
        }
        /** @var list<array<string, mixed>> $indexes */
        $indexes = $indexStatement->fetchAll(PDO::FETCH_ASSOC);
        $primaryColumns = [];
        $naturalColumns = [];
        foreach ($indexes as $index) {
            $name = $index['Key_name'] ?? null;
            $column = $index['Column_name'] ?? null;
            $sequence = $index['Seq_in_index'] ?? null;
            if (!is_string($name) || !is_string($column) || !is_numeric($sequence)) {
                continue;
            }

            if ($name === 'PRIMARY') {
                $primaryColumns[(int) $sequence] = $column;
            }
            if ($name === 'uq_maa_eligibility_rules_natural_identity') {
                $nonUnique = $index['Non_unique'] ?? null;
                self::assertTrue(is_int($nonUnique) || is_string($nonUnique));
                self::assertSame(0, (int) $nonUnique);
                $naturalColumns[(int) $sequence] = $column;
            }
        }
        ksort($primaryColumns);
        ksort($naturalColumns);

        self::assertSame(['id'], array_values($primaryColumns));
        self::assertSame(
            ['subject_type', 'subject_id', 'dimension_key', 'dimension_value'],
            array_values($naturalColumns),
        );
    }

    #[Test]
    public function createReturnsAnActiveRuleAndIdentityReadHydratesIt(): void
    {
        $created = $this->repository->create(
            $this->command('product', '150', 'country', 'EG', RuleEffectEnum::DENY),
        );

        self::assertSame('product', $created->subject->subjectType);
        self::assertSame('150', $created->subject->subjectId);
        self::assertSame('country', $created->dimensionKey);
        self::assertSame('EG', $created->dimensionValue);
        self::assertSame(RuleEffectEnum::DENY, $created->effect);
        self::assertSame(RuleLifecycleEnum::ACTIVE, $created->lifecycle);

        $found = $this->managementQuery->findByIdentity($created->naturalIdentity());
        self::assertNotNull($found);
        self::assertEquals($created->jsonSerialize(), $found->jsonSerialize());
    }

    #[Test]
    public function naturalIdentityRejectsDuplicatesWhetherActiveOrInactive(): void
    {
        $command = $this->command('product', '150', 'country', 'EG');
        $created = $this->repository->create($command);

        $this->assertIdentityConflict($command);
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand($created->naturalIdentity())));
        $this->assertIdentityConflict($command);
    }

    #[Test]
    public function lifecycleAndEffectMutationsAreOrthogonalAndIdempotent(): void
    {
        $created = $this->repository->create($this->command('product', '150', 'country', 'EG'));
        $identity = $created->naturalIdentity();

        self::assertTrue($this->repository->updateEffect(
            new \Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand(
                $identity,
                RuleEffectEnum::ALLOW,
            ),
        ));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand($identity)));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand($identity)));

        $inactive = $this->managementQuery->findByIdentity($identity);
        self::assertNotNull($inactive);
        self::assertSame(RuleEffectEnum::ALLOW, $inactive->effect);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $inactive->lifecycle);

        self::assertTrue($this->repository->updateEffect(
            new \Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand(
                $identity,
                RuleEffectEnum::DENY,
            ),
        ));
        $inactiveAfterEffectUpdate = $this->managementQuery->findByIdentity($identity);
        self::assertNotNull($inactiveAfterEffectUpdate);
        self::assertSame(RuleEffectEnum::DENY, $inactiveAfterEffectUpdate->effect);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $inactiveAfterEffectUpdate->lifecycle);

        self::assertTrue($this->repository->reactivate(
            new \Maatify\Eligibility\Management\Command\ReactivateRuleCommand($identity),
        ));
        self::assertTrue($this->repository->reactivate(
            new \Maatify\Eligibility\Management\Command\ReactivateRuleCommand($identity),
        ));
        $active = $this->managementQuery->findByIdentity($identity);
        self::assertNotNull($active);
        self::assertSame(RuleEffectEnum::DENY, $active->effect);
        self::assertSame(RuleLifecycleEnum::ACTIVE, $active->lifecycle);
    }

    #[Test]
    public function missingIdentityReturnsFalseForEveryStateMutation(): void
    {
        $identity = new RuleIdentity('product', 'missing', 'country', 'EG');

        self::assertFalse($this->repository->updateEffect(
            new \Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand(
                $identity,
                RuleEffectEnum::ALLOW,
            ),
        ));
        self::assertFalse($this->repository->deactivate(new DeactivateRuleCommand($identity)));
        self::assertFalse($this->repository->reactivate(
            new \Maatify\Eligibility\Management\Command\ReactivateRuleCommand($identity),
        ));
    }

    #[Test]
    public function criteriaReturnsBothLifecyclesWithFiltersAndCanonicalOrdering(): void
    {
        $subject = new Subject('product', '150');
        $this->repository->create($this->command('product', '150', 'customer_type', 'wholesale', RuleEffectEnum::DENY));
        $this->repository->create($this->command('product', '150', 'country', 'SA', RuleEffectEnum::DENY));
        $this->repository->create($this->command('product', '150', 'country', 'EG'));
        $this->repository->create($this->command('product', '150', 'customer_type', 'retail'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand(
            new RuleIdentity('product', '150', 'country', 'SA'),
        )));

        $all = $this->managementQuery->findByCriteria(new RuleCriteria($subject), new PageRequest());
        self::assertCount(4, $all->data);
        self::assertSame(4, $all->total);
        self::assertSame(4, $all->filtered);
        self::assertSame(['country', 'country', 'customer_type', 'customer_type'], array_map(
            static fn(Rule $rule): string => $rule->dimensionKey,
            $all->data,
        ));
        self::assertSame(['EG', 'SA', 'retail', 'wholesale'], array_map(
            static fn(Rule $rule): string => $rule->dimensionValue,
            $all->data,
        ));

        self::assertCount(3, $this->managementQuery->findByCriteria(
            new RuleCriteria($subject, lifecycle: RuleLifecycleEnum::ACTIVE),
            new PageRequest(),
        )->data);
        self::assertCount(1, $this->managementQuery->findByCriteria(
            new RuleCriteria($subject, lifecycle: RuleLifecycleEnum::INACTIVE),
            new PageRequest(),
        )->data);
        self::assertCount(2, $this->managementQuery->findByCriteria(
            new RuleCriteria($subject, 'country'),
            new PageRequest(),
        )->data);
        self::assertCount(2, $this->managementQuery->findByCriteria(
            new RuleCriteria($subject, effect: RuleEffectEnum::DENY),
            new PageRequest(),
        )->data);
        self::assertCount(1, $this->managementQuery->findByCriteria(
            new RuleCriteria($subject, lifecycle: RuleLifecycleEnum::ACTIVE, effect: RuleEffectEnum::DENY),
            new PageRequest(),
        )->data);
        self::assertCount(2, $this->managementQuery->findByCriteria(
            new RuleCriteria($subject),
            new PageRequest(perPage: 2),
        )->data);
    }

    #[Test]
    public function activeDimensionKeysExcludeInactiveOnlyDimensionsAndAreCanonical(): void
    {
        $subject = new Subject('product', '150');
        $this->repository->create($this->command('product', '150', 'customer_type', 'retail'));
        $this->repository->create($this->command('product', '150', 'country', 'EG'));
        $this->repository->create($this->command('product', '150', 'country', 'SA'));
        $this->repository->create($this->command('product', '150', 'shipping_provider', 'dhl'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand(
            new RuleIdentity('product', '150', 'shipping_provider', 'dhl'),
        )));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand(
            new RuleIdentity('product', '150', 'country', 'SA'),
        )));

        $keys = $this->managementQuery->findActiveDimensionKeys(new ActiveDimensionKeysCriteria($subject), new PageRequest());

        self::assertSame(['country', 'customer_type'], array_map(
            static fn($dto): string => $dto->dimensionKey,
            $keys->data,
        ));
        self::assertSame(2, $keys->total);
        self::assertSame(2, $keys->filtered);
    }

    #[Test]
    public function activeDimensionKeysPaginateAcrossAPageBoundaryAndAreSubjectIsolated(): void
    {
        $subject = new Subject('product', '150');
        $otherSubject = new Subject('product', '151');
        $expectedKeys = [];
        for ($index = 0; $index < 25; $index++) {
            $dimensionKey = sprintf('dimension_%02d', $index);
            $expectedKeys[] = $dimensionKey;
            $this->repository->create($this->command('product', '150', $dimensionKey, 'value'));
        }
        $this->repository->create($this->command('product', '150', 'inactive_only', 'value'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand(
            new RuleIdentity('product', '150', 'inactive_only', 'value'),
        )));
        $this->repository->create($this->command('product', '151', 'other_subject_dimension', 'value'));
        sort($expectedKeys, SORT_STRING);

        $firstPage = $this->managementQuery->findActiveDimensionKeys(
            new ActiveDimensionKeysCriteria($subject),
            new PageRequest(page: 1, perPage: 20),
        );
        self::assertSame(25, $firstPage->total);
        self::assertSame(25, $firstPage->filtered);
        self::assertCount(20, $firstPage->data);
        self::assertTrue($firstPage->hasNext);
        self::assertFalse($firstPage->hasPrevious);

        $secondPage = $this->managementQuery->findActiveDimensionKeys(
            new ActiveDimensionKeysCriteria($subject),
            new PageRequest(page: 2, perPage: 20),
        );
        self::assertCount(5, $secondPage->data);
        self::assertFalse($secondPage->hasNext);
        self::assertTrue($secondPage->hasPrevious);

        $observedKeys = array_map(
            static fn($dto): string => $dto->dimensionKey,
            [...$firstPage->data, ...$secondPage->data],
        );
        self::assertSame(25, count($observedKeys));
        self::assertSame(25, count(array_unique($observedKeys)));
        self::assertSame($expectedKeys, $observedKeys);
    }

    #[Test]
    public function activeRulesForSubjectsUseBulkLoadingAndExcludeInactiveRules(): void
    {
        self::assertCount(0, $this->activeRuleReader->findActiveForSubjects(new SubjectCollection()));

        $subjects = [
            new Subject('product', '2'),
            new Subject('category', '5'),
            new Subject('product', '1'),
        ];
        $this->repository->create($this->command('product', '2', 'country', 'EG'));
        $this->repository->create($this->command('category', '5', 'country', 'EG'));
        $productOne = $this->repository->create($this->command('product', '1', 'country', 'EG'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand($productOne->naturalIdentity())));

        $active = $this->activeRuleReader->findActiveForSubjects(new SubjectCollection(...$subjects));
        self::assertCount(2, $active);
        self::assertSame(['category', 'product'], array_map(
            static fn(Rule $rule): string => $rule->subject->subjectType,
            $active->items(),
        ));
        self::assertSame(['5', '2'], array_map(
            static fn(Rule $rule): string => $rule->subject->subjectId,
            $active->items(),
        ));

        $manySubjects = [];
        for ($index = 0; $index < 205; $index++) {
            $subject = new Subject('bulk', str_pad((string) $index, 3, '0', STR_PAD_LEFT));
            $manySubjects[] = $subject;
            $this->repository->create($this->command('bulk', $subject->subjectId, 'country', 'EG'));
        }

        $manyRules = $this->activeRuleReader->findActiveForSubjects(new SubjectCollection(...$manySubjects));
        self::assertCount(205, $manyRules);
        self::assertSame('000', $manyRules->items()[0]->subject->subjectId);
        self::assertSame('204', $manyRules->items()[204]->subject->subjectId);
    }

    #[Test]
    public function exactCaseUnicodeAndNonAsciiValuesRoundTripDistinctly(): void
    {
        $values = [
            'EG' => RuleEffectEnum::ALLOW,
            'eg' => RuleEffectEnum::DENY,
            "é" => RuleEffectEnum::ALLOW,
            "e\u{0301}" => RuleEffectEnum::DENY,
            'مرحبا' => RuleEffectEnum::ALLOW,
        ];
        foreach ($values as $value => $effect) {
            $this->repository->create($this->command('product', '150', 'country', $value, $effect));
        }

        foreach ($values as $value => $effect) {
            $found = $this->managementQuery->findByIdentity(new RuleIdentity('product', '150', 'country', $value));
            self::assertNotNull($found);
            self::assertSame($value, $found->dimensionValue);
            self::assertSame($effect, $found->effect);
        }

        self::assertCount(count($values), $this->managementQuery->findByCriteria(
            new RuleCriteria(new Subject('product', '150')),
            new PageRequest(),
        )->data);

        $evaluation = new EligibilityEvaluationService($this->activeRuleReader);
        foreach ($values as $value => $effect) {
            $decision = $evaluation->decide(
                new Subject('product', '150'),
                new Context(ContextDimension::fromStrings('country', $value)),
            );
            self::assertSame(
                $effect === RuleEffectEnum::DENY ? DecisionReasonEnum::DENIED : DecisionReasonEnum::ELIGIBLE,
                $decision->reasonCode,
            );
            self::assertSame([$value], array_map(
                static fn($reference): string => $reference->dimensionValue,
                $decision->dimensionOutcomes->items()[0]->matchedRules->items(),
            ));
        }
    }

    #[Test]
    public function exactByteBoundsAreAcceptedAndOverLimitValuesFailBeforeSql(): void
    {
        $boundary = $this->repository->create(new CreateRuleCommand(
            new Subject(str_repeat('s', 64), str_repeat('i', 191)),
            str_repeat('k', 64),
            str_repeat('v', 255),
            RuleEffectEnum::ALLOW,
        ));
        $found = $this->managementQuery->findByIdentity($boundary->naturalIdentity());
        self::assertNotNull($found);
        self::assertSame(str_repeat('v', 255), $found->dimensionValue);

        $this->assertInvalid(static fn(): CreateRuleCommand => new CreateRuleCommand(
            new Subject(str_repeat('s', 65), 'id'),
            'key',
            'value',
            RuleEffectEnum::ALLOW,
        ));
        $this->assertInvalid(static fn(): CreateRuleCommand => new CreateRuleCommand(
            new Subject('type', str_repeat('i', 192)),
            'key',
            'value',
            RuleEffectEnum::ALLOW,
        ));
        $this->assertInvalid(static fn(): CreateRuleCommand => new CreateRuleCommand(
            new Subject('type', 'id'),
            str_repeat('k', 65),
            'value',
            RuleEffectEnum::ALLOW,
        ));
        $this->assertInvalid(static fn(): CreateRuleCommand => new CreateRuleCommand(
            new Subject('type', 'id'),
            'key',
            str_repeat('v', 256),
            RuleEffectEnum::ALLOW,
        ));

        self::assertCount(1, $this->managementQuery->findByCriteria(
            new RuleCriteria(new Subject(str_repeat('s', 64), str_repeat('i', 191))),
            new PageRequest(),
        )->data);
    }

    #[Test]
    #[DataProvider('repositoryOverLimitComponents')]
    public function repositoryDefensivelyRejectsForgedOverLimitCommands(string $component): void
    {
        $subjectType = $component === 'subject_type'
            ? str_repeat('s', 65)
            : 'type';
        $subjectId = $component === 'subject_id'
            ? str_repeat('i', 192)
            : 'id';
        $dimensionKey = $component === 'dimension_key'
            ? str_repeat('k', 65)
            : 'key';
        $dimensionValue = $component === 'dimension_value'
            ? str_repeat('v', 256)
            : 'value';

        $subject = $this->forgeReadonly(Subject::class, [
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
        ]);
        $command = $this->forgeReadonly(CreateRuleCommand::class, [
            'subject' => $subject,
            'dimensionKey' => $dimensionKey,
            'dimensionValue' => $dimensionValue,
            'effect' => RuleEffectEnum::ALLOW,
        ]);

        $this->assertCreateRejects($command);

        $countStatement = $this->pdo->query('SELECT COUNT(*) FROM `maa_eligibility_rules`');
        if ($countStatement === false) {
            self::fail('Could not inspect the Eligibility row count.');
        }

        self::assertSame(0, (int) $countStatement->fetchColumn());
    }

    /** @return iterable<string, array{string}> */
    public static function repositoryOverLimitComponents(): iterable
    {
        yield 'subject_type' => ['subject_type'];
        yield 'subject_id' => ['subject_id'];
        yield 'dimension_key' => ['dimension_key'];
        yield 'dimension_value' => ['dimension_value'];
    }

    #[Test]
    public function unknownPdoStorageFailurePropagatesUnchanged(): void
    {
        $this->pdo->exec('DROP TABLE `maa_eligibility_rules`');

        try {
            $this->repository->create($this->command('product', '150', 'country', 'EG'));
            self::fail('Expected the missing table PDOException.');
        } catch (PDOException $exception) {
            self::assertSame('42S02', $exception->getCode());
        } finally {
            IntegrationDatabase::applySchema($this->pdo);
        }
    }

    #[Test]
    public function cleanupPhysicallyRemovesOnlyOneSubjectAndIsIdempotent(): void
    {
        $subject = new Subject('product', '150');
        $otherSubject = new Subject('product', '151');
        $first = $this->repository->create($this->command('product', '150', 'country', 'EG'));
        $this->repository->create($this->command('product', '150', 'country', 'SA'));
        $this->repository->create($this->command('product', '151', 'country', 'EG'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand($first->naturalIdentity())));

        $this->repository->cleanupSubject(new CleanupSubjectCommand($subject));
        $this->repository->cleanupSubject(new CleanupSubjectCommand($subject));

        self::assertCount(0, $this->managementQuery->findByCriteria(new RuleCriteria($subject), new PageRequest())->data);
        self::assertCount(1, $this->managementQuery->findByCriteria(new RuleCriteria($otherSubject), new PageRequest())->data);
    }

    #[Test]
    public function paginatedManagementReadsSupportDefaultsExplicitPagesAndPerPageNormalization(): void
    {
        $subject = new Subject('product', '150');

        $emptyResult = $this->managementQuery->findByCriteria(new RuleCriteria($subject), new PageRequest());
        self::assertSame(0, $emptyResult->total);
        self::assertSame(0, $emptyResult->filtered);
        self::assertSame([], $emptyResult->data);
        self::assertFalse($emptyResult->hasNext);
        self::assertFalse($emptyResult->hasPrevious);

        $expectedValues = [];
        for ($index = 0; $index < 25; $index++) {
            $value = sprintf('v%02d', $index);
            $expectedValues[] = $value;
            $this->repository->create($this->command('product', '150', 'country', $value));
        }
        $this->repository->create($this->command('product', '151', 'country', 'other-subject-value'));

        $defaultPage = $this->managementQuery->findByCriteria(new RuleCriteria($subject), new PageRequest());
        self::assertSame(1, $defaultPage->page);
        self::assertSame(20, $defaultPage->perPage);
        self::assertSame(25, $defaultPage->total);
        self::assertSame(25, $defaultPage->filtered);
        self::assertCount(20, $defaultPage->data);
        self::assertTrue($defaultPage->hasNext);
        self::assertFalse($defaultPage->hasPrevious);
        self::assertSame(array_slice($expectedValues, 0, 20), array_map(
            static fn(Rule $rule): string => $rule->dimensionValue,
            $defaultPage->data,
        ));

        $explicitPage = $this->managementQuery->findByCriteria(
            new RuleCriteria($subject),
            new PageRequest(page: 2, perPage: 10),
        );
        self::assertSame(2, $explicitPage->page);
        self::assertSame(10, $explicitPage->perPage);
        self::assertTrue($explicitPage->hasNext);
        self::assertTrue($explicitPage->hasPrevious);
        self::assertSame(array_slice($expectedValues, 10, 10), array_map(
            static fn(Rule $rule): string => $rule->dimensionValue,
            $explicitPage->data,
        ));

        $lastPage = $this->managementQuery->findByCriteria(
            new RuleCriteria($subject),
            new PageRequest(page: 3, perPage: 10),
        );
        self::assertFalse($lastPage->hasNext);
        self::assertTrue($lastPage->hasPrevious);
        self::assertCount(5, $lastPage->data);

        $clamped = $this->managementQuery->findByCriteria(
            new RuleCriteria($subject),
            new PageRequest(perPage: 9999),
        );
        self::assertSame(200, $clamped->perPage);

        foreach ([$defaultPage, $explicitPage, $lastPage] as $page) {
            foreach ($page->data as $rule) {
                self::assertSame('150', $rule->subject->subjectId);
            }
        }
    }

    #[Test]
    public function inspectRulesRejectsUnsupportedSortRequestsAgainstRealPersistence(): void
    {
        $service = new EligibilityManagementService(
            $this->repository,
            $this->managementQuery,
            $this->repository,
            new PdoSavepointTransactionRunner($this->pdo),
        );
        $subject = new Subject('product', '150');
        $this->repository->create($this->command('product', '150', 'country', 'EG'));

        $this->expectException(InvalidEligibilityInputException::class);

        $service->inspectRules(
            new RuleCriteria($subject),
            new PageRequest(sortBy: 'dimension_value', sortDirection: 'DESC'),
        );
    }

    #[Test]
    public function lifecycleSummaryProvesRealPersistenceAggregatesAndSubjectIsolation(): void
    {
        $subject = new Subject('product', '150');
        $otherSubject = new Subject('product', '151');

        $empty = $this->managementQuery->summarizeLifecycle(new RuleLifecycleSummaryCriteria($subject));
        self::assertSame(0, $empty->totalRules);
        self::assertSame(0, $empty->activeRules);
        self::assertSame(0, $empty->inactiveRules);

        $this->repository->create($this->command('product', '150', 'country', 'EG'));
        $this->repository->create($this->command('product', '150', 'country', 'SA'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand(
            new RuleIdentity('product', '150', 'country', 'SA'),
        )));
        $this->repository->create($this->command('product', '150', 'customer_type', 'retail'));
        $this->repository->create($this->command('product', '151', 'country', 'EG'));

        $subjectSummary = $this->managementQuery->summarizeLifecycle(new RuleLifecycleSummaryCriteria($subject));
        self::assertSame(3, $subjectSummary->totalRules);
        self::assertSame(2, $subjectSummary->activeRules);
        self::assertSame(1, $subjectSummary->inactiveRules);

        $dimensionSummary = $this->managementQuery->summarizeLifecycle(
            new RuleLifecycleSummaryCriteria($subject, 'country'),
        );
        self::assertSame(2, $dimensionSummary->totalRules);
        self::assertSame(1, $dimensionSummary->activeRules);
        self::assertSame(1, $dimensionSummary->inactiveRules);

        $otherSummary = $this->managementQuery->summarizeLifecycle(new RuleLifecycleSummaryCriteria($otherSubject));
        self::assertSame(1, $otherSummary->totalRules);
        self::assertSame(1, $otherSummary->activeRules);
        self::assertSame(0, $otherSummary->inactiveRules);
    }

    #[Test]
    public function invalidPersistedEffectBytesAreClassifiedAsPersistedStateFailures(): void
    {
        $this->insertRawRow('product', '150', 'country', 'EG', 'maybe', RuleLifecycleEnum::ACTIVE->value);

        try {
            $this->managementQuery->findByIdentity(new RuleIdentity('product', '150', 'country', 'EG'));
            self::fail('Expected InvalidPersistedRuleStateException for malformed persisted effect.');
        } catch (InvalidPersistedRuleStateException $exception) {
            self::assertInstanceOf(EligibilityExceptionInterface::class, $exception);
            self::assertInstanceOf(MaatifyException::class, $exception);
            self::assertInstanceOf(\ValueError::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function invalidPersistedCanonicalComponentIsClassifiedAsPersistedStateFailure(): void
    {
        $this->insertRawRow('product', '150', 'country', ' EG', RuleEffectEnum::ALLOW->value, RuleLifecycleEnum::ACTIVE->value);

        try {
            $this->managementQuery->findByCriteria(new RuleCriteria(new Subject('product', '150')), new PageRequest());
            self::fail('Expected InvalidPersistedRuleStateException for malformed persisted canonical component.');
        } catch (InvalidPersistedRuleStateException $exception) {
            self::assertInstanceOf(EligibilityExceptionInterface::class, $exception);
            self::assertInstanceOf(InvalidEligibilityInputException::class, $exception->getPrevious());
        }
    }

    private function insertRawRow(
        string $subjectType,
        string $subjectId,
        string $dimensionKey,
        string $dimensionValue,
        string $effect,
        string $lifecycle,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO `maa_eligibility_rules` '
            . '(`subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle`) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([$subjectType, $subjectId, $dimensionKey, $dimensionValue, $effect, $lifecycle]);
    }

    private function command(
        string $subjectType,
        string $subjectId,
        string $dimensionKey,
        string $dimensionValue,
        RuleEffectEnum $effect = RuleEffectEnum::ALLOW,
    ): CreateRuleCommand {
        return new CreateRuleCommand(
            new Subject($subjectType, $subjectId),
            $dimensionKey,
            $dimensionValue,
            $effect,
        );
    }

    private function assertIdentityConflict(CreateRuleCommand $command): void
    {
        try {
            $this->repository->create($command);
            self::fail('Expected a typed natural-identity conflict.');
        } catch (RuleIdentityConflictException $exception) {
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
            self::assertSame($command->dimensionValue, $exception->identity()->dimensionValue);
        }
    }

    private function assertCreateRejects(CreateRuleCommand $command): void
    {
        try {
            $this->repository->create($command);
            self::fail('Expected a typed persistence-bound input error.');
        } catch (InvalidEligibilityInputException) {
            self::addToAssertionCount(1);
        }
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

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $values
     * @return T
     */
    private function forgeReadonly(string $class, array $values): object
    {
        $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        foreach ($values as $propertyName => $value) {
            (new ReflectionProperty($class, $propertyName))->setValue($object, $value);
        }

        return $object;
    }
}
