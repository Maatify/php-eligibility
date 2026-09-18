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
use Maatify\Eligibility\Rule\Repository\RuleCommandRepositoryInterface;
use Maatify\Eligibility\Rule\Repository\RuleManagementQueryInterface;
use Maatify\Eligibility\Rule\Repository\RuleMutationSupportInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Common\Value\SubjectCollection;

/**
 * Deterministic test double for the shared in-memory persistence state.
 */
final class InMemoryRuleRepository implements
    RuleCommandRepositoryInterface,
    RuleMutationSupportInterface,
    RuleManagementQueryInterface,
    ActiveRuleReaderInterface
{
    /** @var array<string, Rule> */
    private array $rules = [];

    public int $bulkReadCount = 0;

    public int $lockCount = 0;

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
        foreach ($this->rules as $key => $rule) {
            if ($this->sameSubject($rule->subject, $command->subject)) {
                unset($this->rules[$key]);
            }
        }
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

        $this->rules[$key] = $rule->withLifecycle($lifecycle);

        return true;
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
