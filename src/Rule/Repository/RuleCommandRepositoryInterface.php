<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Rule\Rule;

interface RuleCommandRepositoryInterface
{
    /** Returns the canonical domain Rule accepted by persistence. */
    public function create(CreateRuleCommand $command): Rule;

    /** Returns false only when the target Rule identity does not exist. */
    public function updateEffect(UpdateRuleEffectCommand $command): bool;

    /** Returns false only when the target Rule identity does not exist. */
    public function deactivate(DeactivateRuleCommand $command): bool;

    /** Returns false only when the target Rule identity does not exist. */
    public function reactivate(ReactivateRuleCommand $command): bool;

    /** Physically removes all Rules for the supplied Subject. */
    public function cleanupSubject(CleanupSubjectCommand $command): void;
}
