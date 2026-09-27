<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository;

use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\Criteria\RuleLifecycleSummaryCriteria;
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyDTO;
use Maatify\Eligibility\Management\DTO\RuleLifecycleSummaryDTO;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use Maatify\Persistence\Pdo\Pagination\PageResult;

/**
 * Management read boundary for identity, paginated criteria, active-dimension,
 * and lifecycle-summary queries.
 */
interface RuleManagementQueryInterface
{
    /** Includes both active and inactive Rules. */
    public function findByIdentity(RuleIdentity $identity): ?Rule;

    /**
     * Returns one page of the Rules matching `$criteria` for one Subject,
     * in the fixed canonical order `dimension_key` ASC, `dimension_value` ASC.
     *
     * `total` counts every persisted Rule for the Subject before the optional
     * `dimensionKey`/`lifecycle`/`effect` filters on `$criteria`; `filtered`
     * counts Rules after those filters.
     *
     * `$pageRequest->sortBy`/`sortDirection` accept only both `null` (the
     * canonical order) or the explicit equivalent pair `dimension_key` +
     * `ASC`; any other explicit sort request throws
     * `InvalidEligibilityInputException` rather than being normalized or
     * silently falling back.
     *
     * @return PageResult<Rule>
     */
    public function findByCriteria(RuleCriteria $criteria, PageRequest $pageRequest): PageResult;

    /**
     * Returns one page of distinct dimension keys with at least one active
     * Rule for the Subject, in canonical ascending order. `total` and
     * `filtered` are always equal for this query, because no optional domain
     * filter exists beyond its intrinsic active-visibility contract.
     *
     * `$pageRequest->sortBy`/`sortDirection` accept the same canonical shape
     * as {@see findByCriteria()}: only both `null`, or the explicit
     * equivalent pair `dimension_key` + `ASC`; any other explicit sort
     * request throws `InvalidEligibilityInputException`.
     *
     * @return PageResult<ActiveDimensionKeyDTO>
     */
    public function findActiveDimensionKeys(
        ActiveDimensionKeysCriteria $criteria,
        PageRequest $pageRequest,
    ): PageResult;

    /**
     * Returns the Rule lifecycle count summary for the supplied criteria
     * scope. Malformed persisted state that this package owns a semantic
     * classification for (for example an inconsistent independent total
     * caused by an unrecognized persisted lifecycle value) throws
     * `InvalidPersistedRuleStateException`; unknown/external storage
     * failures are not wrapped and propagate unchanged.
     */
    public function summarizeLifecycle(RuleLifecycleSummaryCriteria $criteria): RuleLifecycleSummaryDTO;
}
