<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Common\Validation\CanonicalString;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use PDO;
use PDOStatement;

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
}
