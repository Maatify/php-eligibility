<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

/**
 * @internal Transitional transaction/savepoint boundary for Eligibility mutations.
 *
 * This contract is retained only until the later shared Persistence transaction
 * migration. It intentionally owns no Rule commands or mutation-support reads.
 */
interface RuleReplacementRepositoryInterface
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
}
