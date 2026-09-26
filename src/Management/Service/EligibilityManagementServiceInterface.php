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
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyCollectionDTO;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\ValueObject\RuleIdentity;

/**
 * Public management boundary for Rule persistence, inspection, and lifecycle state.
 */
interface EligibilityManagementServiceInterface
{
    public function createRule(CreateRuleCommand $command): Rule;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function inspectRule(RuleIdentity $identity): Rule;

    public function inspectRules(RuleCriteria $criteria): RuleCollection;

    public function inspectActiveDimensionKeys(ActiveDimensionKeysCriteria $query): ActiveDimensionKeyCollectionDTO;

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
