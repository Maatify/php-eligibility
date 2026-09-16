<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Application\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Application\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Application\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Validation\CanonicalString;
use Maatify\Eligibility\Value\Subject;
use Maatify\Eligibility\Value\SubjectCollection;
use PDO;
use PDOException;
use PDOStatement;

final class PdoRuleRepository implements RuleReplacementRepositoryInterface
{
    private const TABLE = 'maa_eligibility_rules';

    private const SUBJECT_LOCK_TABLE = 'maa_eligibility_subject_locks';

    private const BULK_SUBJECT_CHUNK_SIZE = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(CreateRuleCommand $command): Rule
    {
        $subjectType = CanonicalString::validateSubjectType($command->subject->subjectType);
        $subjectId = CanonicalString::validateSubjectId($command->subject->subjectId);
        $dimensionKey = CanonicalString::validateDimensionKey($command->dimensionKey);
        $dimensionValue = CanonicalString::validateDimensionValue($command->dimensionValue);

        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::TABLE . '` '
            . '(`subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle`) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
        );

        try {
            $this->bindAndExecute($statement, [
                $subjectType,
                $subjectId,
                $dimensionKey,
                $dimensionValue,
                $command->effect->value,
                RuleLifecycleEnum::ACTIVE->value,
            ]);
        } catch (PDOException $exception) {
            if (MySqlDuplicateKeyClassifier::isDuplicate($exception)) {
                throw new RuleIdentityConflictException(
                    new RuleIdentity($subjectType, $subjectId, $dimensionKey, $dimensionValue),
                    $exception,
                );
            }

            throw $exception;
        }

        return Rule::active(
            $command->subject,
            $dimensionKey,
            $dimensionValue,
            $command->effect,
        );
    }

    public function findByIdentity(RuleIdentity $identity): ?Rule
    {
        $parameters = $this->identityParameters($identity);
        $rows = $this->fetchRows(
            'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
            . 'FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ? '
            . 'LIMIT 1',
            $parameters,
        );

        $row = $rows[0] ?? null;

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByCriteria(RuleCriteria $criteria): RuleCollection
    {
        $conditions = [
            '`subject_type` = ?',
            '`subject_id` = ?',
        ];
        $parameters = [
            CanonicalString::validateSubjectType($criteria->subject->subjectType),
            CanonicalString::validateSubjectId($criteria->subject->subjectId),
        ];

        if ($criteria->dimensionKey !== null) {
            $conditions[] = '`dimension_key` = ?';
            $parameters[] = CanonicalString::validateDimensionKey($criteria->dimensionKey);
        }

        if ($criteria->lifecycle !== null) {
            $conditions[] = '`lifecycle` = ?';
            $parameters[] = $criteria->lifecycle->value;
        }

        $parameters[] = $criteria->maxResults;
        $rows = $this->fetchRows(
            'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
            . 'FROM `' . self::TABLE . '` WHERE ' . implode(' AND ', $conditions) . ' '
            . 'ORDER BY `subject_type`, `subject_id`, `dimension_key`, `dimension_value` LIMIT ?',
            $parameters,
        );

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = $this->hydrate($row);
        }

        return new RuleCollection(...$rules);
    }

    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection
    {
        $subjectItems = $subjects->items();
        if ($subjectItems === []) {
            return new RuleCollection();
        }

        $rules = [];
        $subjectCount = count($subjectItems);
        for ($offset = 0; $offset < $subjectCount; $offset += self::BULK_SUBJECT_CHUNK_SIZE) {
            $chunk = array_slice($subjectItems, $offset, self::BULK_SUBJECT_CHUNK_SIZE);
            $subjectConditions = [];
            $parameters = [RuleLifecycleEnum::ACTIVE->value];

            foreach ($chunk as $subject) {
                $subjectConditions[] = '(`subject_type` = ? AND `subject_id` = ?)';
                $parameters[] = CanonicalString::validateSubjectType($subject->subjectType);
                $parameters[] = CanonicalString::validateSubjectId($subject->subjectId);
            }

            $rows = $this->fetchRows(
                'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
                . 'FROM `' . self::TABLE . '` WHERE `lifecycle` = ? AND ('
                . implode(' OR ', $subjectConditions) . ') '
                . 'ORDER BY `subject_type`, `subject_id`, `dimension_key`, `dimension_value`',
                $parameters,
            );

            foreach ($rows as $row) {
                $rules[] = $this->hydrate($row);
            }
        }

        return new RuleCollection(...$rules);
    }

    public function findActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection
    {
        $rows = $this->fetchRows(
            'SELECT DISTINCT `dimension_key` FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `lifecycle` = ?',
            [
                CanonicalString::validateSubjectType($query->subject->subjectType),
                CanonicalString::validateSubjectId($query->subject->subjectId),
                RuleLifecycleEnum::ACTIVE->value,
            ],
        );

        $dimensionKeys = [];
        foreach ($rows as $row) {
            $dimensionKeys[] = CanonicalString::validateDimensionKey(
                $this->rowString($row, 'dimension_key'),
            );
        }

        return new ActiveDimensionKeyCollection(...$dimensionKeys);
    }

    public function updateEffect(UpdateRuleEffectCommand $command): bool
    {
        $identity = $this->boundedIdentity($command->identity);

        return $this->updateAndCheckIdentity(
            'UPDATE `' . self::TABLE . '` SET `effect` = ? '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ?',
            [$command->effect->value, ...$this->identityParameters($identity)],
            $identity,
        );
    }

    public function deactivate(DeactivateRuleCommand $command): bool
    {
        $identity = $this->boundedIdentity($command->identity);

        return $this->updateAndCheckIdentity(
            'UPDATE `' . self::TABLE . '` SET `lifecycle` = ? '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ?',
            [RuleLifecycleEnum::INACTIVE->value, ...$this->identityParameters($identity)],
            $identity,
        );
    }

    public function reactivate(ReactivateRuleCommand $command): bool
    {
        $identity = $this->boundedIdentity($command->identity);

        return $this->updateAndCheckIdentity(
            'UPDATE `' . self::TABLE . '` SET `lifecycle` = ? '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ?',
            [RuleLifecycleEnum::ACTIVE->value, ...$this->identityParameters($identity)],
            $identity,
        );
    }

    public function cleanupSubject(CleanupSubjectCommand $command): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM `' . self::TABLE . '` WHERE `subject_type` = ? AND `subject_id` = ?',
        );
        $this->bindAndExecute($statement, [
            CanonicalString::validateSubjectType($command->subject->subjectType),
            CanonicalString::validateSubjectId($command->subject->subjectId),
        ]);
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function createOperationSavepoint(): string
    {
        $savepoint = 'maa_eligibility_sp_' . bin2hex(random_bytes(16));
        $this->executeSavepointStatement('SAVEPOINT ' . $savepoint);

        return $savepoint;
    }

    public function rollbackToOperationSavepoint(string $savepoint): void
    {
        $this->executeSavepointStatement('ROLLBACK TO SAVEPOINT ' . $this->savepointName($savepoint));
    }

    public function releaseOperationSavepoint(string $savepoint): void
    {
        $this->executeSavepointStatement('RELEASE SAVEPOINT ' . $this->savepointName($savepoint));
    }

    public function lockSubjectForMutation(Subject $subject): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::SUBJECT_LOCK_TABLE . '` '
            . '(`subject_type`, `subject_id`) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE `id` = `id`',
        );
        $this->bindAndExecute($statement, [
            CanonicalString::validateSubjectType($subject->subjectType),
            CanonicalString::validateSubjectId($subject->subjectId),
        ]);
    }

    public function findAllForSubjectDimension(Subject $subject, string $dimensionKey): RuleCollection
    {
        $rows = $this->fetchRows(
            'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
            . 'FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? '
            . 'ORDER BY `dimension_value`',
            [
                CanonicalString::validateSubjectType($subject->subjectType),
                CanonicalString::validateSubjectId($subject->subjectId),
                CanonicalString::validateDimensionKey($dimensionKey),
            ],
        );

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = $this->hydrate($row);
        }

        return new RuleCollection(...$rules);
    }

    public function deleteSubjectCoordination(Subject $subject): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM `' . self::SUBJECT_LOCK_TABLE . '` WHERE `subject_type` = ? AND `subject_id` = ?',
        );
        $this->bindAndExecute($statement, [
            CanonicalString::validateSubjectType($subject->subjectType),
            CanonicalString::validateSubjectId($subject->subjectId),
        ]);
    }

    /** @param list<string|int> $parameters */
    private function bindAndExecute(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $index => $parameter) {
            $statement->bindValue(
                $index + 1,
                $parameter,
                is_int($parameter) ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }

        $statement->execute();
    }

    /**
     * @param list<string|int> $parameters
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $this->bindAndExecute($statement, $parameters);

        /** @var array<int, mixed> $rawRows */
        $rawRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (!is_array($rawRow)) {
                throw new \UnexpectedValueException('Expected an array row from PDO.');
            }

            $row = [];
            foreach ($rawRow as $column => $value) {
                if (!is_string($column)) {
                    throw new \UnexpectedValueException('Expected string column names from PDO.');
                }

                $row[$column] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Rule
    {
        $subjectType = CanonicalString::validateSubjectType($this->rowString($row, 'subject_type'));
        $subjectId = CanonicalString::validateSubjectId($this->rowString($row, 'subject_id'));
        $dimensionKey = CanonicalString::validateDimensionKey($this->rowString($row, 'dimension_key'));
        $dimensionValue = CanonicalString::validateDimensionValue($this->rowString($row, 'dimension_value'));

        return new Rule(
            new Subject($subjectType, $subjectId),
            $dimensionKey,
            $dimensionValue,
            RuleEffectEnum::from($this->rowString($row, 'effect')),
            RuleLifecycleEnum::from($this->rowString($row, 'lifecycle')),
        );
    }

    /** @param array<string, mixed> $row */
    private function rowString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Expected string column %s.', $column));
        }

        return $value;
    }

    private function executeSavepointStatement(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    private function savepointName(string $savepoint): string
    {
        if (preg_match('/\\Amaa_eligibility_sp_[0-9a-f]{32}\\z/D', $savepoint) !== 1) {
            throw new \InvalidArgumentException('Invalid package operation savepoint name.');
        }

        return $savepoint;
    }

    /** @return list<string> */
    private function identityParameters(RuleIdentity $identity): array
    {
        return [
            $identity->subjectType,
            $identity->subjectId,
            $identity->dimensionKey,
            $identity->dimensionValue,
        ];
    }

    private function boundedIdentity(RuleIdentity $identity): RuleIdentity
    {
        return new RuleIdentity(
            CanonicalString::validateSubjectType($identity->subjectType),
            CanonicalString::validateSubjectId($identity->subjectId),
            CanonicalString::validateDimensionKey($identity->dimensionKey),
            CanonicalString::validateDimensionValue($identity->dimensionValue),
        );
    }

    /** @param list<string|int> $parameters */
    private function updateAndCheckIdentity(string $sql, array $parameters, RuleIdentity $identity): bool
    {
        $statement = $this->pdo->prepare($sql);
        $this->bindAndExecute($statement, $parameters);

        if ($statement->rowCount() > 0) {
            return true;
        }

        return $this->exists($identity);
    }

    private function exists(RuleIdentity $identity): bool
    {
        $rows = $this->fetchRows(
            'SELECT 1 AS `found` FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ? '
            . 'LIMIT 1',
            $this->identityParameters($identity),
        );

        return $rows !== [];
    }
}
