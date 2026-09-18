<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Common\Validation\CanonicalString;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use PDO;
use PDOException;

final class PdoRuleCommandRepository implements
    RuleCommandRepositoryInterface,
    RuleMutationSupportInterface
{
    use PdoRuleHydrationTrait;

    private const TABLE = 'maa_eligibility_rules';

    private const SUBJECT_LOCK_TABLE = 'maa_eligibility_subject_locks';

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
