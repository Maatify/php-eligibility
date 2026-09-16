<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Support;

use Maatify\Eligibility\Application\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Application\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Application\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Rule\Repository\RuleReplacementRepositoryInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Value\Subject;
use Maatify\Eligibility\Value\SubjectCollection;

/**
 * Real-boundary decorator used only to inject a deterministic post-write fault.
 */
final class FaultingRuleReplacementRepository implements RuleReplacementRepositoryInterface
{
    private bool $failed = false;

    private bool $cleanupFailed = false;

    public function __construct(
        private readonly RuleReplacementRepositoryInterface $repository,
        private readonly \Throwable $failure,
        private readonly ?\Throwable $cleanupFailure = null,
    ) {
    }

    public function create(CreateRuleCommand $command): Rule
    {
        $rule = $this->repository->create($command);
        if (!$this->failed) {
            $this->failed = true;
            throw $this->failure;
        }

        return $rule;
    }

    public function findByIdentity(RuleIdentity $identity): ?Rule
    {
        return $this->repository->findByIdentity($identity);
    }

    public function findByCriteria(RuleCriteria $criteria): RuleCollection
    {
        return $this->repository->findByCriteria($criteria);
    }

    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection
    {
        return $this->repository->findActiveForSubjects($subjects);
    }

    public function findActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection
    {
        return $this->repository->findActiveDimensionKeys($query);
    }

    public function updateEffect(UpdateRuleEffectCommand $command): bool
    {
        return $this->repository->updateEffect($command);
    }

    public function deactivate(DeactivateRuleCommand $command): bool
    {
        return $this->repository->deactivate($command);
    }

    public function reactivate(ReactivateRuleCommand $command): bool
    {
        return $this->repository->reactivate($command);
    }

    public function cleanupSubject(CleanupSubjectCommand $command): void
    {
        $this->repository->cleanupSubject($command);
        if ($this->cleanupFailure !== null && !$this->cleanupFailed) {
            $this->cleanupFailed = true;
            throw $this->cleanupFailure;
        }
    }

    public function inTransaction(): bool
    {
        return $this->repository->inTransaction();
    }

    public function beginTransaction(): void
    {
        $this->repository->beginTransaction();
    }

    public function commit(): void
    {
        $this->repository->commit();
    }

    public function rollBack(): void
    {
        $this->repository->rollBack();
    }

    public function createOperationSavepoint(): string
    {
        return $this->repository->createOperationSavepoint();
    }

    public function rollbackToOperationSavepoint(string $savepoint): void
    {
        $this->repository->rollbackToOperationSavepoint($savepoint);
    }

    public function releaseOperationSavepoint(string $savepoint): void
    {
        $this->repository->releaseOperationSavepoint($savepoint);
    }

    public function lockSubjectForMutation(Subject $subject): void
    {
        $this->repository->lockSubjectForMutation($subject);
    }

    public function findAllForSubjectDimension(Subject $subject, string $dimensionKey): RuleCollection
    {
        return $this->repository->findAllForSubjectDimension($subject, $dimensionKey);
    }

    public function deleteSubjectCoordination(Subject $subject): void
    {
        $this->repository->deleteSubjectCoordination($subject);
    }
}
