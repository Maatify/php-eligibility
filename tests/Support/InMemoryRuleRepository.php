<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Support;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Rule\Repository\ActiveRuleReaderInterface;
use Maatify\Eligibility\Rule\Repository\RuleManagementQueryInterface;
use Maatify\Eligibility\Rule\Repository\RuleReplacementRepositoryInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Common\Value\SubjectCollection;

/**
 * Deterministic test double for the replaceable persistence boundary.
 */
final class InMemoryRuleRepository implements
    RuleReplacementRepositoryInterface,
    RuleManagementQueryInterface,
    ActiveRuleReaderInterface
{
    /** @var array<string, Rule> */
    private array $rules = [];

    /** @var array<string, Rule>|null */
    private ?array $transactionSnapshot = null;

    /** @var array<string, array<string, Rule>> */
    private array $savepointSnapshots = [];

    public int $bulkReadCount = 0;

    public int $lockCount = 0;

    public int $beginCount = 0;

    public int $commitCount = 0;

    public int $rollbackCount = 0;

    public int $savepointCreateCount = 0;

    public int $savepointRollbackCount = 0;

    public int $savepointReleaseCount = 0;

    public ?\Throwable $failure = null;

    public ?\Throwable $savepointRollbackFailure = null;

    public function seed(Rule ...$rules): void
    {
        foreach ($rules as $rule) {
            $this->rules[$this->identityKey($rule->naturalIdentity())] = $rule;
        }
    }

    public function create(CreateRuleCommand $command): Rule
    {
        $rule = Rule::active(
            $command->subject,
            $command->dimensionKey,
            $command->dimensionValue,
            $command->effect,
        );
        $key = $this->identityKey($rule->naturalIdentity());

        if (isset($this->rules[$key])) {
            throw new RuleIdentityConflictException($rule->naturalIdentity());
        }

        $this->throwInjectedFailure();
        $this->rules[$key] = $rule;

        return $rule;
    }

    public function findByIdentity(RuleIdentity $identity): ?Rule
    {
        return $this->rules[$this->identityKey($identity)] ?? null;
    }

    public function findByCriteria(RuleCriteria $criteria): RuleCollection
    {
        $rules = [];
        foreach ($this->rules as $rule) {
            if (!$this->sameSubject($rule->subject, $criteria->subject)) {
                continue;
            }
            if ($criteria->dimensionKey !== null && $rule->dimensionKey !== $criteria->dimensionKey) {
                continue;
            }
            if ($criteria->lifecycle !== null && $rule->lifecycle !== $criteria->lifecycle) {
                continue;
            }

            $rules[] = $rule;
        }

        $ordered = new RuleCollection(...$rules);

        return new RuleCollection(...array_slice($ordered->items(), 0, $criteria->maxResults));
    }

    public function findActiveForSubjects(SubjectCollection $subjects): RuleCollection
    {
        $this->bulkReadCount++;
        $rules = [];
        foreach ($this->rules as $rule) {
            if ($rule->lifecycle !== RuleLifecycleEnum::ACTIVE) {
                continue;
            }

            foreach ($subjects as $subject) {
                if ($this->sameSubject($rule->subject, $subject)) {
                    $rules[] = $rule;
                    break;
                }
            }
        }

        return new RuleCollection(...$rules);
    }

    public function findActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection
    {
        $keys = [];
        foreach ($this->rules as $rule) {
            if (
                $rule->lifecycle === RuleLifecycleEnum::ACTIVE
                && $this->sameSubject($rule->subject, $query->subject)
            ) {
                $keys[] = $rule->dimensionKey;
            }
        }

        return new ActiveDimensionKeyCollection(...array_values(array_unique($keys)));
    }

    public function updateEffect(UpdateRuleEffectCommand $command): bool
    {
        $key = $this->identityKey($command->identity);
        $rule = $this->rules[$key] ?? null;
        if ($rule === null) {
            return false;
        }

        $this->throwInjectedFailure();
        $this->rules[$key] = $rule->withEffect($command->effect);

        return true;
    }

    public function deactivate(DeactivateRuleCommand $command): bool
    {
        return $this->setLifecycle($command->identity, RuleLifecycleEnum::INACTIVE);
    }

    public function reactivate(ReactivateRuleCommand $command): bool
    {
        return $this->setLifecycle($command->identity, RuleLifecycleEnum::ACTIVE);
    }

    public function cleanupSubject(CleanupSubjectCommand $command): void
    {
        $this->throwInjectedFailure();
        foreach ($this->rules as $key => $rule) {
            if ($this->sameSubject($rule->subject, $command->subject)) {
                unset($this->rules[$key]);
            }
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionSnapshot !== null;
    }

    public function beginTransaction(): void
    {
        if ($this->inTransaction()) {
            throw new \LogicException('Nested test transactions are not supported.');
        }

        $this->transactionSnapshot = $this->rules;
        $this->savepointSnapshots = [];
        $this->beginCount++;
    }

    public function commit(): void
    {
        $this->transactionSnapshot = null;
        $this->savepointSnapshots = [];
        $this->commitCount++;
    }

    public function rollBack(): void
    {
        if (!$this->inTransaction()) {
            throw new \LogicException('No test transaction is active.');
        }

        $this->rules = $this->transactionSnapshot ?? [];
        $this->transactionSnapshot = null;
        $this->savepointSnapshots = [];
        $this->rollbackCount++;
    }

    public function createOperationSavepoint(): string
    {
        if (!$this->inTransaction()) {
            throw new \LogicException('An operation savepoint requires an active test transaction.');
        }

        $savepoint = 'maa_eligibility_sp_' . (++$this->savepointCreateCount);
        $this->savepointSnapshots[$savepoint] = $this->rules;

        return $savepoint;
    }

    public function rollbackToOperationSavepoint(string $savepoint): void
    {
        if (!array_key_exists($savepoint, $this->savepointSnapshots)) {
            throw new \LogicException('Unknown test operation savepoint.');
        }

        if ($this->savepointRollbackFailure !== null) {
            throw $this->savepointRollbackFailure;
        }

        $this->rules = $this->savepointSnapshots[$savepoint];
        $this->savepointRollbackCount++;
    }

    public function releaseOperationSavepoint(string $savepoint): void
    {
        if (!array_key_exists($savepoint, $this->savepointSnapshots)) {
            throw new \LogicException('Unknown test operation savepoint.');
        }

        unset($this->savepointSnapshots[$savepoint]);
        $this->savepointReleaseCount++;
    }

    public function lockSubjectForMutation(Subject $subject): void
    {
        $this->lockCount++;
    }

    public function findAllForSubjectDimension(Subject $subject, string $dimensionKey): RuleCollection
    {
        $rules = [];
        foreach ($this->rules as $rule) {
            if (
                $this->sameSubject($rule->subject, $subject)
                && $rule->dimensionKey === $dimensionKey
            ) {
                $rules[] = $rule;
            }
        }

        return new RuleCollection(...$rules);
    }

    public function deleteSubjectCoordination(Subject $subject): void
    {
    }

    /** @return list<Rule> */
    public function allRules(): array
    {
        return array_values($this->rules);
    }

    private function setLifecycle(RuleIdentity $identity, RuleLifecycleEnum $lifecycle): bool
    {
        $key = $this->identityKey($identity);
        $rule = $this->rules[$key] ?? null;
        if ($rule === null) {
            return false;
        }

        $this->throwInjectedFailure();
        $this->rules[$key] = $rule->withLifecycle($lifecycle);

        return true;
    }

    private function throwInjectedFailure(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }

    private function sameSubject(Subject $left, Subject $right): bool
    {
        return $left->subjectType === $right->subjectType
            && $left->subjectId === $right->subjectId;
    }

    private function identityKey(RuleIdentity $identity): string
    {
        return $this->stringKey($identity->subjectType)
            . $this->stringKey($identity->subjectId)
            . $this->stringKey($identity->dimensionKey)
            . $this->stringKey($identity->dimensionValue);
    }

    private function stringKey(string $value): string
    {
        return strlen($value) . ':' . $value;
    }
}
