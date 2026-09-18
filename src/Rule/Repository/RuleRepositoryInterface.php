<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Common\Value\SubjectCollection;

interface RuleRepositoryInterface
{
    /** Returns the canonical domain Rule accepted by persistence. */
    public function create(CreateRuleCommand $command): Rule;

    /** Includes both active and inactive Rules. */
    public function findByIdentity(RuleIdentity $identity): ?Rule;

    /** Applies the bounded management criteria, including lifecycle filtering. */
    public function findByCriteria(RuleCriteria $criteria): RuleCollection;

    /** Loads active Rules for all supplied Subjects in a bounded bulk operation. */
    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection;

    /** Returns active dimension keys for one Subject in canonical order. */
    public function findActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection;

    /** Returns false only when the target Rule identity does not exist. */
    public function updateEffect(UpdateRuleEffectCommand $command): bool;

    /** Returns false only when the target Rule identity does not exist. */
    public function deactivate(DeactivateRuleCommand $command): bool;

    /** Returns false only when the target Rule identity does not exist. */
    public function reactivate(ReactivateRuleCommand $command): bool;

    /** Physically removes all Rules for the supplied Subject. */
    public function cleanupSubject(CleanupSubjectCommand $command): void;
}
