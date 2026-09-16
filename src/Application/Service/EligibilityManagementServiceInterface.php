<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Service;

use Maatify\Eligibility\Application\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Application\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Application\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleIdentity;

interface EligibilityManagementServiceInterface
{
    public function createRule(CreateRuleCommand $command): Rule;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function inspectRule(RuleIdentity $identity): Rule;

    public function inspectRules(RuleCriteria $criteria): RuleCollection;

    public function inspectActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function updateRuleEffect(UpdateRuleEffectCommand $command): void;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function deactivateRule(DeactivateRuleCommand $command): void;

    /** @throws \Maatify\Eligibility\Exception\RuleNotFoundException */
    public function reactivateRule(ReactivateRuleCommand $command): void;

    public function replaceDimensionRules(ReplaceDimensionRulesCommand $command): void;

    public function cleanupSubject(CleanupSubjectCommand $command): void;
}
