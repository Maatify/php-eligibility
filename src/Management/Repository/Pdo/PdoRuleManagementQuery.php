<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository\Pdo;

use Maatify\Eligibility\Common\CanonicalString;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\InvalidPersistedRuleStateException;
use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\Criteria\RuleLifecycleSummaryCriteria;
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyDTO;
use Maatify\Eligibility\Management\DTO\RuleLifecycleSummaryDTO;
use Maatify\Eligibility\Management\Repository\RuleManagementQueryInterface;
use Maatify\Eligibility\Repository\Pdo\PdoRuleHydrationTrait;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\PdoPaginator;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PDO;

/**
 * Direct-PDO management query adapter.
 *
 * Identity reads include inactive Rules. Paginated reads apply the caller's
 * optional dimension/lifecycle/effect filters and delegate page/per-page/sort
 * mechanics to the shared `maatify/persistence` paginator; Eligibility owns
 * only the domain filter/count SQL and row mapping. All returned collections
 * are canonicalized by their value-object constructors.
 */
final class PdoRuleManagementQuery implements RuleManagementQueryInterface
{
    use PdoRuleHydrationTrait;

    private const TABLE = 'maa_eligibility_rules';

    private const RULE_SORT_KEY = 'dimension_key';

    private const RULE_TIE_BREAKER_SORT_KEY = 'dimension_value';

    public function __construct(
        private readonly PDO $pdo,
        private readonly PdoPaginator $paginator = new PdoPaginator(),
    ) {}

    /** Reads either active or inactive state and returns null only when the identity is absent. */
    public function findByIdentity(RuleIdentity $identity): ?Rule
    {
        $rows = $this->fetchRows(
            'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
            . 'FROM `' . self::TABLE . '` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ? '
            . 'LIMIT 1',
            [
                CanonicalString::validateSubjectType($identity->subjectType),
                CanonicalString::validateSubjectId($identity->subjectId),
                CanonicalString::validateDimensionKey($identity->dimensionKey),
                CanonicalString::validateDimensionValue($identity->dimensionValue),
            ],
        );

        $row = $rows[0] ?? null;

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Applies optional dimension/lifecycle/effect filters and returns one
     * canonically ordered page of the matching Rules for the Subject.
     *
     * @return PageResult<Rule>
     */
    public function findByCriteria(RuleCriteria $criteria, PageRequest $pageRequest): PageResult
    {
        self::assertCanonicalRuleSort($pageRequest);

        $baseParams = [
            'subject_type' => CanonicalString::validateSubjectType($criteria->subject->subjectType),
            'subject_id' => CanonicalString::validateSubjectId($criteria->subject->subjectId),
        ];

        $filterConditions = [];
        $filterParams = $baseParams;

        if ($criteria->dimensionKey !== null) {
            $filterConditions[] = '`dimension_key` = :dimension_key';
            $filterParams['dimension_key'] = CanonicalString::validateDimensionKey($criteria->dimensionKey);
        }

        if ($criteria->lifecycle !== null) {
            $filterConditions[] = '`lifecycle` = :lifecycle';
            $filterParams['lifecycle'] = $criteria->lifecycle->value;
        }

        if ($criteria->effect !== null) {
            $filterConditions[] = '`effect` = :effect';
            $filterParams['effect'] = $criteria->effect->value;
        }

        $baseWhere = '`subject_type` = :subject_type AND `subject_id` = :subject_id';
        $filteredWhere = implode(' AND ', [$baseWhere, ...$filterConditions]);

        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE ' . $baseWhere,
            totalParams: $baseParams,
            filteredCountSql: 'SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE ' . $filteredWhere,
            filteredCountParams: $filterParams,
            dataSql: 'SELECT `subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle` '
                . 'FROM `' . self::TABLE . '` WHERE ' . $filteredWhere,
            dataParams: $filterParams,
        );

        return $this->paginator->paginate(
            $this->pdo,
            $descriptor,
            $pageRequest,
            self::rulePaginationConfig(),
            fn(array $row): Rule => $this->hydrate($row),
        );
    }

    /**
     * Returns one canonically ordered page of distinct active dimension keys
     * for a Subject. Total and filtered are always equal for this query.
     *
     * @return PageResult<ActiveDimensionKeyDTO>
     */
    public function findActiveDimensionKeys(
        ActiveDimensionKeysCriteria $criteria,
        PageRequest $pageRequest,
    ): PageResult {
        self::assertCanonicalRuleSort($pageRequest);

        $params = [
            'subject_type' => CanonicalString::validateSubjectType($criteria->subject->subjectType),
            'subject_id' => CanonicalString::validateSubjectId($criteria->subject->subjectId),
            'lifecycle' => RuleLifecycleEnum::ACTIVE->value,
        ];
        $where = '`subject_type` = :subject_type AND `subject_id` = :subject_id AND `lifecycle` = :lifecycle';
        $countSql = 'SELECT COUNT(*) FROM (SELECT DISTINCT `dimension_key` FROM `' . self::TABLE . '` '
            . 'WHERE ' . $where . ') AS `distinct_active_dimensions`';

        $descriptor = new PdoPaginationQueryDescriptor(
            totalSql: $countSql,
            totalParams: $params,
            filteredCountSql: $countSql,
            filteredCountParams: $params,
            dataSql: 'SELECT DISTINCT `dimension_key` FROM `' . self::TABLE . '` WHERE ' . $where,
            dataParams: $params,
        );

        return $this->paginator->paginate(
            $this->pdo,
            $descriptor,
            $pageRequest,
            self::activeDimensionKeyPaginationConfig(),
            fn(array $row): ActiveDimensionKeyDTO => new ActiveDimensionKeyDTO(
                $this->validatedColumn(
                    $row,
                    'dimension_key',
                    static fn(string $value): string => CanonicalString::validateDimensionKey($value),
                ),
            ),
        );
    }

    /**
     * Computes the Rule lifecycle count summary for the supplied criteria scope
     * in one aggregate query, and classifies an inconsistent independent total
     * (for example caused by an unrecognized persisted lifecycle byte) as
     * malformed persisted state rather than silently undercounting it.
     */
    public function summarizeLifecycle(RuleLifecycleSummaryCriteria $criteria): RuleLifecycleSummaryDTO
    {
        $conditions = ['`subject_type` = ?', '`subject_id` = ?'];
        $parameters = [
            CanonicalString::validateSubjectType($criteria->subject->subjectType),
            CanonicalString::validateSubjectId($criteria->subject->subjectId),
        ];

        if ($criteria->dimensionKey !== null) {
            $conditions[] = '`dimension_key` = ?';
            $parameters[] = CanonicalString::validateDimensionKey($criteria->dimensionKey);
        }

        $rows = $this->fetchRows(
            'SELECT '
            . 'COUNT(*) AS `total_count`, '
            . 'SUM(CASE WHEN `lifecycle` = ? THEN 1 ELSE 0 END) AS `active_count`, '
            . 'SUM(CASE WHEN `lifecycle` = ? THEN 1 ELSE 0 END) AS `inactive_count` '
            . 'FROM `' . self::TABLE . '` WHERE ' . implode(' AND ', $conditions),
            [
                RuleLifecycleEnum::ACTIVE->value,
                RuleLifecycleEnum::INACTIVE->value,
                ...$parameters,
            ],
        );

        $row = $rows[0] ?? [];
        $totalCount = $this->exactNonNegativeIntegerColumn($row, 'total_count');
        $active = $this->exactNonNegativeIntegerColumn($row, 'active_count');
        $inactive = $this->exactNonNegativeIntegerColumn($row, 'inactive_count');

        if ($totalCount !== $active + $inactive) {
            throw new InvalidPersistedRuleStateException(sprintf(
                'Persisted Rule lifecycle state is inconsistent for the requested scope: '
                . 'independent total %d does not equal active (%d) plus inactive (%d). '
                . 'This indicates an unrecognized persisted lifecycle value.',
                $totalCount,
                $active,
                $inactive,
            ));
        }

        return new RuleLifecycleSummaryDTO($totalCount, $active, $inactive);
    }

    /**
     * Reads one required aggregate column and accepts only an exact
     * non-negative integer representation (a native int, or a string of
     * decimal digits only); decimal, scientific, or negative representations
     * are rejected rather than silently coerced.
     *
     * @param array<string, mixed> $row
     */
    private function exactNonNegativeIntegerColumn(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidPersistedRuleStateException(
                    sprintf('Aggregate column `%s` must not be negative.', $column),
                );
            }

            return $value;
        }

        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidPersistedRuleStateException(
            sprintf('Expected an exact non-negative integer aggregate column `%s`.', $column),
        );
    }

    /**
     * Enforces the frozen public sort-request contract at the repository
     * boundary: `null`/`null` (canonical order) or the explicit equivalent
     * `dimension_key` + `ASC`. `dimension_value` is an internal tie-breaker
     * only and MUST NOT be accepted as an explicit public primary sort; any
     * other explicit request is invalid Eligibility Management input.
     */
    private static function assertCanonicalRuleSort(PageRequest $pageRequest): void
    {
        if ($pageRequest->sortBy === null && $pageRequest->sortDirection === null) {
            return;
        }

        if ($pageRequest->sortBy === self::RULE_SORT_KEY && $pageRequest->sortDirection === 'ASC') {
            return;
        }

        throw new InvalidEligibilityInputException(
            'Unsupported Rule management sort request; only canonical ascending dimension_key order is supported.',
        );
    }

    private static function rulePaginationConfig(): PaginationConfig
    {
        return new PaginationConfig(
            new SortWhitelist([
                self::RULE_SORT_KEY => self::RULE_SORT_KEY,
                self::RULE_TIE_BREAKER_SORT_KEY => self::RULE_TIE_BREAKER_SORT_KEY,
            ]),
            self::RULE_SORT_KEY,
            SortDirectionEnum::ASC,
            self::RULE_TIE_BREAKER_SORT_KEY,
            SortDirectionEnum::ASC,
        );
    }

    private static function activeDimensionKeyPaginationConfig(): PaginationConfig
    {
        return new PaginationConfig(
            new SortWhitelist([self::RULE_SORT_KEY => self::RULE_SORT_KEY]),
            self::RULE_SORT_KEY,
            SortDirectionEnum::ASC,
            self::RULE_SORT_KEY,
            SortDirectionEnum::ASC,
        );
    }
}
