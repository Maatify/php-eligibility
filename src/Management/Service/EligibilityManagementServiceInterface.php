<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Service;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
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
 * Public management boundary for Rule persistence, paginated inspection, and
 * lifecycle state.
 */
interface EligibilityManagementServiceInterface
{
    public function createRule(CreateRuleCommand $command): Rule;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function inspectRule(RuleIdentity $identity): Rule;

    /**
     * Returns one canonically ordered page of Rules matching the criteria.
     *
     * `PageRequest.sortBy`/`sortDirection` accept only `null` (canonical order)
     * or the explicit equivalent `dimension_key` ascending; any other explicit
     * sort request is rejected with `InvalidEligibilityInputException`.
     *
     * @return PageResult<Rule>
     */
    public function inspectRules(RuleCriteria $criteria, PageRequest $pageRequest): PageResult;

    /**
     * Returns one canonically ordered page of active dimension keys for the
     * Subject. `total` and `filtered` are always equal for this query.
     *
     * @return PageResult<ActiveDimensionKeyDTO>
     */
    public function inspectActiveDimensionKeys(
        ActiveDimensionKeysCriteria $criteria,
        PageRequest $pageRequest,
    ): PageResult;

    /** Returns the Rule lifecycle count summary for the supplied criteria scope. */
    public function inspectRuleLifecycleSummary(RuleLifecycleSummaryCriteria $criteria): RuleLifecycleSummaryDTO;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function updateRuleEffect(UpdateRuleEffectCommand $command): void;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function deactivateRule(DeactivateRuleCommand $command): void;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function reactivateRule(ReactivateRuleCommand $command): void;

    /**
     * Atomically makes one Subject + dimension match the desired active Rule set.
     * Existing identities are updated/reactivated, omitted active identities are
     * deactivated, and unrelated dimensions remain untouched.
     */
    public function replaceDimensionRules(ReplaceDimensionRulesCommand $command): void;

    /** Removes all Rules and package-owned coordination metadata for the Subject atomically. */
    public function cleanupSubject(CleanupSubjectCommand $command): void;
}
