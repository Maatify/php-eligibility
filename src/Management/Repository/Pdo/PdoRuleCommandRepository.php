<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository\Pdo;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Management\Repository\RuleCommandRepositoryInterface;
use Maatify\Eligibility\Management\Repository\RuleMutationSupportInterface;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\Exception\RuleConcurrencyConflictException;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\Repository\Pdo\PdoRuleHydrationTrait;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Direct-PDO Rule mutation and Subject-coordination adapter.
 *
 * Natural-identity uniqueness conflicts and unresolved lock-wait-timeout/deadlock
 * conflicts are translated to typed package exceptions while unrelated PDO
 * failures propagate. Lock creation is transaction-scoped and is used by the
 * management service for atomic desired-state replacement.
 */
final class PdoRuleCommandRepository implements
    RuleCommandRepositoryInterface,
    RuleMutationSupportInterface
{
    use PdoRuleHydrationTrait;

    private const TABLE = 'maa_eligibility_rules';

    private const SUBJECT_LOCK_TABLE = 'maa_eligibility_subject_locks';

    public function __construct(private readonly PDO $pdo) {}

    /** Persists a new active Rule and translates duplicate natural identity to a typed conflict. */
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

            if (MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception)) {
                throw new RuleConcurrencyConflictException(previous: $exception);
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

    /** Mutates effect state and returns false only when the natural identity is absent. */
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

    /** Marks the identity inactive without deleting it; false means the identity is absent. */
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

    /** Marks the identity active without creating a duplicate; false means it is absent. */
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

    /** Deletes all persisted Rules for the Subject; transaction ownership remains with the service. */
    public function cleanupSubject(CleanupSubjectCommand $command): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM `' . self::TABLE . '` WHERE `subject_type` = ? AND `subject_id` = ?',
        );
        $this->executeMutation($statement, [
            CanonicalString::validateSubjectType($command->subject->subjectType),
            CanonicalString::validateSubjectId($command->subject->subjectId),
        ]);
    }

    /** Creates or retains the package-owned Subject coordination row for the caller's transaction. */
    public function lockSubjectForMutation(Subject $subject): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO `' . self::SUBJECT_LOCK_TABLE . '` '
            . '(`subject_type`, `subject_id`) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE `id` = `id`',
        );
        $this->executeMutation($statement, [
            CanonicalString::validateSubjectType($subject->subjectType),
            CanonicalString::validateSubjectId($subject->subjectId),
        ]);
    }

    /** Reads every Rule for one Subject + dimension without the bounded management result limit. */
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

    /** Removes coordination metadata after Subject cleanup has completed successfully. */
    public function deleteSubjectCoordination(Subject $subject): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM `' . self::SUBJECT_LOCK_TABLE . '` WHERE `subject_type` = ? AND `subject_id` = ?',
        );
        $this->executeMutation($statement, [
            CanonicalString::validateSubjectType($subject->subjectType),
            CanonicalString::validateSubjectId($subject->subjectId),
        ]);
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
        $this->executeMutation($statement, $parameters);

        if ($statement->rowCount() > 0) {
            return true;
        }

        return $this->exists($identity);
    }

    /**
     * Executes a command/mutation statement and translates a proven MySQL/MariaDB
     * lock-wait-timeout or deadlock driver code to the typed concurrency conflict.
     * Every other PDO failure propagates unchanged.
     *
     * @param list<string|int> $parameters
     */
    private function executeMutation(PDOStatement $statement, array $parameters): void
    {
        try {
            $this->bindAndExecute($statement, $parameters);
        } catch (PDOException $exception) {
            if (MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception)) {
                throw new RuleConcurrencyConflictException(previous: $exception);
            }

            throw $exception;
        }
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
