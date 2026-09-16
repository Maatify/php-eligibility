<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Application\Service;

use Closure;
use Maatify\Eligibility\Application\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Application\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Application\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Application\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Result\ActiveDimensionKeyCollection;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\Rule\Repository\RuleReplacementRepositoryInterface;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleCollection;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;

final class EligibilityManagementService implements EligibilityManagementServiceInterface
{
    public function __construct(private readonly RuleReplacementRepositoryInterface $repository)
    {
    }

    public function createRule(CreateRuleCommand $command): Rule
    {
        return $this->repository->create($command);
    }

    public function inspectRule(RuleIdentity $identity): Rule
    {
        $rule = $this->repository->findByIdentity($identity);
        if ($rule === null) {
            throw new RuleNotFoundException($identity);
        }

        return $rule;
    }

    public function inspectRules(RuleCriteria $criteria): RuleCollection
    {
        return $this->repository->findByCriteria($criteria);
    }

    public function inspectActiveDimensionKeys(ActiveDimensionKeysQuery $query): ActiveDimensionKeyCollection
    {
        return $this->repository->findActiveDimensionKeys($query);
    }

    public function updateRuleEffect(UpdateRuleEffectCommand $command): void
    {
        $this->assertMutationSucceeded(
            $this->repository->updateEffect($command),
            $command->identity,
        );
    }

    public function deactivateRule(DeactivateRuleCommand $command): void
    {
        $this->assertMutationSucceeded(
            $this->repository->deactivate($command),
            $command->identity,
        );
    }

    public function reactivateRule(ReactivateRuleCommand $command): void
    {
        $this->assertMutationSucceeded(
            $this->repository->reactivate($command),
            $command->identity,
        );
    }

    public function replaceDimensionRules(ReplaceDimensionRulesCommand $command): void
    {
        $this->executeTransaction(function () use ($command): void {
            $this->repository->lockSubjectForMutation($command->subject);

            $existingRules = $this->repository->findAllForSubjectDimension(
                $command->subject,
                $command->dimensionKey,
            );
            /** @var array<string, Rule> $existingByValue */
            $existingByValue = [];
            foreach ($existingRules as $existingRule) {
                $existingByValue[$this->stringKey($existingRule->dimensionValue)] = $existingRule;
            }

            /** @var array<string, true> $desiredValues */
            $desiredValues = [];
            foreach ($command->desiredRules as $desiredRule) {
                $valueKey = $this->stringKey($desiredRule->dimensionValue);
                $desiredValues[$valueKey] = true;
                $existingRule = $existingByValue[$valueKey] ?? null;

                if ($existingRule === null) {
                    $this->repository->create(new CreateRuleCommand(
                        $command->subject,
                        $command->dimensionKey,
                        $desiredRule->dimensionValue,
                        $desiredRule->effect,
                    ));
                    continue;
                }

                if ($existingRule->effect !== $desiredRule->effect) {
                    $this->assertMutationSucceeded(
                        $this->repository->updateEffect(new UpdateRuleEffectCommand(
                            $existingRule->naturalIdentity(),
                            $desiredRule->effect,
                        )),
                        $existingRule->naturalIdentity(),
                    );
                }

                if ($existingRule->lifecycle === RuleLifecycleEnum::INACTIVE) {
                    $this->assertMutationSucceeded(
                        $this->repository->reactivate(new ReactivateRuleCommand(
                            $existingRule->naturalIdentity(),
                        )),
                        $existingRule->naturalIdentity(),
                    );
                }
            }

            foreach ($existingRules as $existingRule) {
                if (
                    $existingRule->lifecycle === RuleLifecycleEnum::ACTIVE
                    && !isset($desiredValues[$this->stringKey($existingRule->dimensionValue)])
                ) {
                    $this->assertMutationSucceeded(
                        $this->repository->deactivate(new DeactivateRuleCommand(
                            $existingRule->naturalIdentity(),
                        )),
                        $existingRule->naturalIdentity(),
                    );
                }
            }
        });
    }

    public function cleanupSubject(CleanupSubjectCommand $command): void
    {
        $this->executeTransaction(function () use ($command): void {
            $this->repository->lockSubjectForMutation($command->subject);
            $this->repository->cleanupSubject($command);
            $this->repository->deleteSubjectCoordination($command->subject);
        });
    }

    private function assertMutationSucceeded(bool $succeeded, RuleIdentity $identity): void
    {
        if (!$succeeded) {
            throw new RuleNotFoundException($identity);
        }
    }

    private function executeTransaction(Closure $operation): void
    {
        $ownsTransaction = !$this->repository->inTransaction();
        if ($ownsTransaction) {
            $this->repository->beginTransaction();
        }

        try {
            $operation();
            if ($ownsTransaction) {
                $this->repository->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->repository->inTransaction()) {
                try {
                    $this->repository->rollBack();
                } catch (\Throwable) {
                    // The operation's original Throwable is the contractually visible failure.
                }
            }

            throw $exception;
        }
    }

    private function stringKey(string $value): string
    {
        return strlen($value) . ':' . $value;
    }
}
