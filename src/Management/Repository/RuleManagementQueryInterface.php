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
     * Applies the management criteria filters and returns one page of the
     * matching Rules in canonical `dimension_key`, `dimension_value` order.
     *
     * @return PageResult<Rule>
     */
    public function findByCriteria(RuleCriteria $criteria, PageRequest $pageRequest): PageResult;

    /**
     * Returns one page of distinct active dimension keys for a Subject in
     * canonical ascending order.
     *
     * @return PageResult<ActiveDimensionKeyDTO>
     */
    public function findActiveDimensionKeys(
        ActiveDimensionKeysCriteria $criteria,
        PageRequest $pageRequest,
    ): PageResult;

    /** Returns the Rule lifecycle count summary for the supplied criteria scope. */
    public function summarizeLifecycle(RuleLifecycleSummaryCriteria $criteria): RuleLifecycleSummaryDTO;
}
