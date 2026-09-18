<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Rule\RuleCollection;

/**
 * @internal Eligibility-specific persistence support for atomic mutation flows.
 */
interface RuleMutationSupportInterface
{
    /** Acquires the package-owned Subject coordination lock inside a transaction. */
    public function lockSubjectForMutation(Subject $subject): void;

    /** Loads every Rule for one Subject + dimension without the management read bound. */
    public function findAllForSubjectDimension(Subject $subject, string $dimensionKey): RuleCollection;

    /** Removes the package-owned coordination metadata after Subject cleanup. */
    public function deleteSubjectCoordination(Subject $subject): void;
}
