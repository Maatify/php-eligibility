<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Support;

use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Rule\Repository\RuleCommandRepositoryInterface;
use Maatify\Eligibility\Rule\Repository\RuleMutationSupportInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;

/**
 * Real-boundary decorator used only to inject deterministic post-write faults.
 */
final class FaultingRuleMutationRepository implements RuleCommandRepositoryInterface, RuleMutationSupportInterface
{
    private bool $failed = false;

    private bool $cleanupFailed = false;

    public function __construct(
        private readonly RuleCommandRepositoryInterface & RuleMutationSupportInterface $repository,
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
