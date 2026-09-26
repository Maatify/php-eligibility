<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Service;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\DTO\ActiveDimensionKeyCollectionDTO;
use Maatify\Eligibility\Management\Service\EligibilityManagementServiceInterface;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\Management\Repository\RuleCommandRepositoryInterface;
use Maatify\Eligibility\Management\Repository\RuleManagementQueryInterface;
use Maatify\Eligibility\Management\Repository\RuleMutationSupportInterface;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\ValueObject\RuleCollection;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;

final class EligibilityManagementService implements EligibilityManagementServiceInterface
{
    public function __construct(
        private readonly RuleCommandRepositoryInterface $commandRepository,
        private readonly RuleManagementQueryInterface $managementQuery,
        private readonly RuleMutationSupportInterface $mutationSupport,
        private readonly SavepointTransactionRunnerInterface $transactionRunner,
    )
    {
    }

    public function createRule(CreateRuleCommand $command): Rule
    {
        return $this->commandRepository->create($command);
    }

    public function inspectRule(RuleIdentity $identity): Rule
    {
        $rule = $this->managementQuery->findByIdentity($identity);
        if ($rule === null) {
            throw new RuleNotFoundException($identity);
        }

        return $rule;
    }

    public function inspectRules(RuleCriteria $criteria): RuleCollection
    {
        return $this->managementQuery->findByCriteria($criteria);
    }

    public function inspectActiveDimensionKeys(ActiveDimensionKeysCriteria $query): ActiveDimensionKeyCollectionDTO
    {
        return $this->managementQuery->findActiveDimensionKeys($query);
    }

    public function updateRuleEffect(UpdateRuleEffectCommand $command): void
    {
        $this->assertMutationSucceeded(
            $this->commandRepository->updateEffect($command),
            $command->identity,
        );
    }

    public function deactivateRule(DeactivateRuleCommand $command): void
    {
        $this->assertMutationSucceeded(
            $this->commandRepository->deactivate($command),
            $command->identity,
        );
    }

    public function reactivateRule(ReactivateRuleCommand $command): void
    {
        $this->assertMutationSucceeded(
            $this->commandRepository->reactivate($command),
            $command->identity,
        );
    }

    public function replaceDimensionRules(ReplaceDimensionRulesCommand $command): void
    {
        $this->transactionRunner->run(function () use ($command): void {
            $this->mutationSupport->lockSubjectForMutation($command->subject);

            $existingRules = $this->mutationSupport->findAllForSubjectDimension(
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
                    $this->commandRepository->create(new CreateRuleCommand(
                        $command->subject,
                        $command->dimensionKey,
                        $desiredRule->dimensionValue,
                        $desiredRule->effect,
                    ));
                    continue;
                }

                if ($existingRule->effect !== $desiredRule->effect) {
                    $this->assertMutationSucceeded(
                        $this->commandRepository->updateEffect(new UpdateRuleEffectCommand(
                            $existingRule->naturalIdentity(),
                            $desiredRule->effect,
                        )),
                        $existingRule->naturalIdentity(),
                    );
                }

                if ($existingRule->lifecycle === RuleLifecycleEnum::INACTIVE) {
                    $this->assertMutationSucceeded(
                        $this->commandRepository->reactivate(new ReactivateRuleCommand(
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
                        $this->commandRepository->deactivate(new DeactivateRuleCommand(
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
        $this->transactionRunner->run(function () use ($command): void {
            $this->mutationSupport->lockSubjectForMutation($command->subject);
            $this->commandRepository->cleanupSubject($command);
            $this->mutationSupport->deleteSubjectCoordination($command->subject);
        });
    }

    private function assertMutationSucceeded(bool $succeeded, RuleIdentity $identity): void
    {
        if (!$succeeded) {
            throw new RuleNotFoundException($identity);
        }
    }

    private function stringKey(string $value): string
    {
        return strlen($value) . ':' . $value;
    }
}
