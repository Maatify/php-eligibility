<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Repository\Pdo;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\InvalidPersistedRuleStateException;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\Subject;
use PDO;
use PDOStatement;
use ValueError;

/** @internal Shared direct-PDO row binding and Rule hydration only. */
trait PdoRuleHydrationTrait
{
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
                throw new InvalidPersistedRuleStateException('Expected an array row from persisted Eligibility storage.');
            }

            $row = [];
            foreach ($rawRow as $column => $value) {
                if (!is_string($column)) {
                    throw new InvalidPersistedRuleStateException('Expected string column names from persisted Eligibility storage.');
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
        $subjectType = $this->validatedColumn(
            $row,
            'subject_type',
            static fn(string $value): string => CanonicalString::validateSubjectType($value),
        );
        $subjectId = $this->validatedColumn(
            $row,
            'subject_id',
            static fn(string $value): string => CanonicalString::validateSubjectId($value),
        );
        $dimensionKey = $this->validatedColumn(
            $row,
            'dimension_key',
            static fn(string $value): string => CanonicalString::validateDimensionKey($value),
        );
        $dimensionValue = $this->validatedColumn(
            $row,
            'dimension_value',
            static fn(string $value): string => CanonicalString::validateDimensionValue($value),
        );
        $effect = $this->validatedColumn(
            $row,
            'effect',
            static fn(string $value): RuleEffectEnum => RuleEffectEnum::from($value),
        );
        $lifecycle = $this->validatedColumn(
            $row,
            'lifecycle',
            static fn(string $value): RuleLifecycleEnum => RuleLifecycleEnum::from($value),
        );

        return new Rule(
            new Subject($subjectType, $subjectId),
            $dimensionKey,
            $dimensionValue,
            $effect,
            $lifecycle,
        );
    }

    /**
     * Reads one required string column and classifies malformed persisted
     * Eligibility Rule state as {@see InvalidPersistedRuleStateException},
     * preserving the original validation failure as `previous`.
     *
     * @template T
     * @param array<string, mixed> $row
     * @param callable(string): T $validator
     * @return T
     */
    private function validatedColumn(array $row, string $column, callable $validator): mixed
    {
        $raw = $this->rowString($row, $column);

        try {
            return $validator($raw);
        } catch (InvalidEligibilityInputException|ValueError $exception) {
            throw new InvalidPersistedRuleStateException(
                sprintf('Persisted column `%s` is invalid for Eligibility Rule state.', $column),
                $exception,
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function rowString(array $row, string $column): string
    {
        if (!array_key_exists($column, $row)) {
            throw new InvalidPersistedRuleStateException(
                sprintf('Missing required persisted column `%s`.', $column),
            );
        }

        $value = $row[$column];
        if (!is_string($value)) {
            throw new InvalidPersistedRuleStateException(
                sprintf('Expected string column `%s` in persisted Eligibility storage.', $column),
            );
        }

        return $value;
    }
}
