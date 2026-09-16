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

    /** Creates and returns an operation-local savepoint inside the active transaction. */
    public function createOperationSavepoint(): string;

    /** Rolls back only the operation-local savepoint, leaving the outer transaction active. */
    public function rollbackToOperationSavepoint(string $savepoint): void;

    /** Releases an operation-local savepoint without changing transaction ownership. */
    public function releaseOperationSavepoint(string $savepoint): void;

    /** Acquires the package-owned Subject coordination lock inside a transaction. */
    public function lockSubjectForMutation(Subject $subject): void;

    /** Loads every Rule for one Subject + dimension without the management read bound. */
    public function findAllForSubjectDimension(Subject $subject, string $dimensionKey): RuleCollection;

    /** Removes the package-owned coordination metadata after Subject cleanup. */
    public function deleteSubjectCoordination(Subject $subject): void;
}
