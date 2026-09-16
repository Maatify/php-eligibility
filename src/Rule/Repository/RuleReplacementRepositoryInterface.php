<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Value\Subject;

/**
 * @internal Persistence capabilities required only by the replacement service.
 */
interface RuleReplacementRepositoryInterface extends RuleRepositoryInterface
{
    public function inTransaction(): bool;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    /** Acquires the package-owned Subject coordination lock inside a transaction. */
    public function lockSubjectForMutation(Subject $subject): void;

    /** Loads every Rule for one Subject + dimension without the management read bound. */
    public function findAllForSubjectDimension(Subject $subject, string $dimensionKey): RuleCollection;

    /** Removes the package-owned coordination metadata after Subject cleanup. */
    public function deleteSubjectCoordination(Subject $subject): void;
}
