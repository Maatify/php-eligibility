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
     * Returns one canonically ordered page of Rules matching the criteria,
     * in the fixed order `dimension_key` ASC, `dimension_value` ASC.
     *
     * `$pageRequest->sortBy`/`sortDirection` accept only exactly one of the
     * two following shapes: both `null` (implicit canonical order), or the
     * explicit pair `sortBy = 'dimension_key'` and `sortDirection = 'ASC'`.
     * Any other explicit combination — including `dimension_key` with a
     * `null` direction, any `dimension_value` sort, or a `DESC` direction —
     * is rejected.
     *
     * @return PageResult<Rule>
     * @throws \Maatify\Eligibility\Exception\InvalidEligibilityInputException if the sort request does not match one of those two shapes
     */
    public function inspectRules(RuleCriteria $criteria, PageRequest $pageRequest): PageResult;

    /**
     * Returns one canonically ordered page of active dimension keys for the
     * Subject. `total` and `filtered` are always equal for this query,
     * because no optional domain filter exists beyond its intrinsic
     * active-visibility contract.
     *
     * `$pageRequest->sortBy`/`sortDirection` accept the same two canonical
     * shapes as {@see inspectRules()}: both `null`, or the explicit pair
     * `sortBy = 'dimension_key'` and `sortDirection = 'ASC'`.
     *
     * @return PageResult<ActiveDimensionKeyDTO>
     * @throws \Maatify\Eligibility\Exception\InvalidEligibilityInputException if the sort request does not match one of those two shapes
     */
    public function inspectActiveDimensionKeys(
        ActiveDimensionKeysCriteria $criteria,
        PageRequest $pageRequest,
    ): PageResult;

    /**
     * Returns the Rule lifecycle count summary for the supplied criteria
     * scope. Malformed persisted state that this package owns a semantic
     * classification for (for example an inconsistent independent total
     * caused by an unrecognized persisted lifecycle value) surfaces as
     * `InvalidPersistedRuleStateException`; unknown/external infrastructure
     * failures are not wrapped and propagate unchanged.
     *
     * @throws \Maatify\Eligibility\Exception\InvalidPersistedRuleStateException if the persisted lifecycle state cannot be classified
     */
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
