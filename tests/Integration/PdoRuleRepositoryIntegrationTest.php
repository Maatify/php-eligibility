<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Integration;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Evaluation\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Rule\Repository\PdoRuleRepository;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Evaluation\Value\ContextDimension;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Common\Value\SubjectCollection;
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

    private PdoRuleRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = IntegrationDatabase::connect();
        IntegrationDatabase::applySchema($this->pdo);
        IntegrationDatabase::clearRules($this->pdo);
        $this->repository = new PdoRuleRepository($this->pdo);
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

        $found = $this->repository->findByIdentity($created->naturalIdentity());
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

        $found = $this->repository->findByIdentity($created->naturalIdentity());
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

        $inactive = $this->repository->findByIdentity($identity);
        self::assertNotNull($inactive);
        self::assertSame(RuleEffectEnum::ALLOW, $inactive->effect);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $inactive->lifecycle);

        self::assertTrue($this->repository->updateEffect(
            new \Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand(
                $identity,
                RuleEffectEnum::DENY,
            ),
        ));
        $inactiveAfterEffectUpdate = $this->repository->findByIdentity($identity);
        self::assertNotNull($inactiveAfterEffectUpdate);
        self::assertSame(RuleEffectEnum::DENY, $inactiveAfterEffectUpdate->effect);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $inactiveAfterEffectUpdate->lifecycle);

        self::assertTrue($this->repository->reactivate(
            new \Maatify\Eligibility\Management\Command\ReactivateRuleCommand($identity),
        ));
        self::assertTrue($this->repository->reactivate(
            new \Maatify\Eligibility\Management\Command\ReactivateRuleCommand($identity),
        ));
        $active = $this->repository->findByIdentity($identity);
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

        $all = $this->repository->findByCriteria(new RuleCriteria($subject));
        self::assertCount(4, $all);
        self::assertSame(['country', 'country', 'customer_type', 'customer_type'], array_map(
            static fn (Rule $rule): string => $rule->dimensionKey,
            $all->items(),
        ));
        self::assertSame(['EG', 'SA', 'retail', 'wholesale'], array_map(
            static fn (Rule $rule): string => $rule->dimensionValue,
            $all->items(),
        ));

        self::assertCount(3, $this->repository->findByCriteria(
            new RuleCriteria($subject, lifecycle: RuleLifecycleEnum::ACTIVE),
        ));
        self::assertCount(1, $this->repository->findByCriteria(
            new RuleCriteria($subject, lifecycle: RuleLifecycleEnum::INACTIVE),
        ));
        self::assertCount(2, $this->repository->findByCriteria(new RuleCriteria($subject, 'country')));
        self::assertCount(2, $this->repository->findByCriteria(new RuleCriteria($subject, maxResults: 2)));
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

        $keys = $this->repository->findActiveDimensionKeys(new ActiveDimensionKeysQuery($subject));

        self::assertSame(['country', 'customer_type'], $keys->items());
    }

    #[Test]
    public function activeRulesForSubjectsUseBulkLoadingAndExcludeInactiveRules(): void
    {
        self::assertCount(0, $this->repository->findActiveForSubjects(new SubjectCollection()));

        $subjects = [
            new Subject('product', '2'),
            new Subject('category', '5'),
            new Subject('product', '1'),
        ];
        $this->repository->create($this->command('product', '2', 'country', 'EG'));
        $this->repository->create($this->command('category', '5', 'country', 'EG'));
        $productOne = $this->repository->create($this->command('product', '1', 'country', 'EG'));
        self::assertTrue($this->repository->deactivate(new DeactivateRuleCommand($productOne->naturalIdentity())));

        $active = $this->repository->findActiveForSubjects(new SubjectCollection(...$subjects));
        self::assertCount(2, $active);
        self::assertSame(['category', 'product'], array_map(
            static fn (Rule $rule): string => $rule->subject->subjectType,
            $active->items(),
        ));
        self::assertSame(['5', '2'], array_map(
            static fn (Rule $rule): string => $rule->subject->subjectId,
            $active->items(),
        ));

        $manySubjects = [];
        for ($index = 0; $index < 205; $index++) {
            $subject = new Subject('bulk', str_pad((string) $index, 3, '0', STR_PAD_LEFT));
            $manySubjects[] = $subject;
            $this->repository->create($this->command('bulk', $subject->subjectId, 'country', 'EG'));
        }

        $manyRules = $this->repository->findActiveForSubjects(new SubjectCollection(...$manySubjects));
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
            $found = $this->repository->findByIdentity(new RuleIdentity('product', '150', 'country', $value));
            self::assertNotNull($found);
            self::assertSame($value, $found->dimensionValue);
            self::assertSame($effect, $found->effect);
        }

        self::assertCount(count($values), $this->repository->findByCriteria(new RuleCriteria(
            new Subject('product', '150'),
        )));

        $evaluation = new EligibilityEvaluationService($this->repository);
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
                static fn ($reference): string => $reference->dimensionValue,
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
        $found = $this->repository->findByIdentity($boundary->naturalIdentity());
        self::assertNotNull($found);
        self::assertSame(str_repeat('v', 255), $found->dimensionValue);

        $this->assertInvalid(static fn (): CreateRuleCommand => new CreateRuleCommand(
            new Subject(str_repeat('s', 65), 'id'),
            'key',
            'value',
            RuleEffectEnum::ALLOW,
        ));
        $this->assertInvalid(static fn (): CreateRuleCommand => new CreateRuleCommand(
            new Subject('type', str_repeat('i', 192)),
            'key',
            'value',
            RuleEffectEnum::ALLOW,
        ));
        $this->assertInvalid(static fn (): CreateRuleCommand => new CreateRuleCommand(
            new Subject('type', 'id'),
            str_repeat('k', 65),
            'value',
            RuleEffectEnum::ALLOW,
        ));
        $this->assertInvalid(static fn (): CreateRuleCommand => new CreateRuleCommand(
            new Subject('type', 'id'),
            'key',
            str_repeat('v', 256),
            RuleEffectEnum::ALLOW,
        ));

        self::assertCount(1, $this->repository->findByCriteria(new RuleCriteria(
            new Subject(str_repeat('s', 64), str_repeat('i', 191)),
        )));
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

        self::assertCount(0, $this->repository->findByCriteria(new RuleCriteria($subject)));
        self::assertCount(1, $this->repository->findByCriteria(new RuleCriteria($otherSubject)));
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
