<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\FaultingRuleReplacementRepository;
use Maatify\Eligibility\Tests\Support\InMemoryRuleRepository;
use Maatify\Eligibility\Common\Value\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeManagementServiceTest extends TestCase
{
    #[Test]
    public function createInspectReadsAndActiveDimensionKeysDelegateTypedResults(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = new EligibilityManagementService($repository, $repository);
        $command = new CreateRuleCommand(new Subject('product', '150'), 'country', 'EG', RuleEffectEnum::ALLOW);

        $created = $service->createRule($command);
        $inspected = $service->inspectRule($created->naturalIdentity());

        self::assertSame(RuleLifecycleEnum::ACTIVE, $created->lifecycle);
        self::assertSame($created->jsonSerialize(), $inspected->jsonSerialize());
        self::assertSame(['country'], $service->inspectActiveDimensionKeys(
            new ActiveDimensionKeysQuery($command->subject),
        )->items());
    }

    #[Test]
    public function inspectMissingAndMissingMutationsProduceTypedNotFound(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = new EligibilityManagementService($repository, $repository);
        $identity = new RuleIdentity('product', 'missing', 'country', 'EG');

        try {
            $service->inspectRule($identity);
            self::fail('Expected inspectRule to throw RuleNotFoundException.');
        } catch (RuleNotFoundException $exception) {
            self::assertSame($identity, $exception->identity());
        }

        $this->assertMissingMutationProducesTypedNotFound(
            static function () use ($service, $identity): void {
                $service->deactivateRule(new DeactivateRuleCommand($identity));
            },
            $identity,
        );
        $this->assertMissingMutationProducesTypedNotFound(
            static function () use ($service, $identity): void {
                $service->reactivateRule(new ReactivateRuleCommand($identity));
            },
            $identity,
        );
        $this->assertMissingMutationProducesTypedNotFound(
            static function () use ($service, $identity): void {
                $service->updateRuleEffect(new UpdateRuleEffectCommand($identity, RuleEffectEnum::DENY));
            },
            $identity,
        );
    }

    #[Test]
    public function managementReadsExposeInactiveRulesAndLifecycleFilters(): void
    {
        $repository = new InMemoryRuleRepository();
        $subject = new Subject('product', '150');
        $repository->seed(
            $this->rule('EG', RuleEffectEnum::ALLOW),
            $this->rule('SA', RuleEffectEnum::DENY)->withLifecycle(RuleLifecycleEnum::INACTIVE),
        );
        $service = new EligibilityManagementService($repository, $repository);

        self::assertCount(2, $service->inspectRules(new RuleCriteria($subject)));
        self::assertCount(1, $service->inspectRules(new RuleCriteria(
            $subject,
            lifecycle: RuleLifecycleEnum::INACTIVE,
        )));
    }

    #[Test]
    public function lifecycleAndEffectCommandsAreOrthogonalAndIdempotent(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = new EligibilityManagementService($repository, $repository);
        $rule = $service->createRule(new CreateRuleCommand(
            new Subject('product', '150'),
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        ));
        $identity = $rule->naturalIdentity();

        $service->deactivateRule(new DeactivateRuleCommand($identity));
        $service->deactivateRule(new DeactivateRuleCommand($identity));
        $service->updateRuleEffect(new UpdateRuleEffectCommand($identity, RuleEffectEnum::DENY));
        $service->updateRuleEffect(new UpdateRuleEffectCommand($identity, RuleEffectEnum::DENY));
        self::assertSame(RuleLifecycleEnum::INACTIVE, $service->inspectRule($identity)->lifecycle);
        self::assertSame(RuleEffectEnum::DENY, $service->inspectRule($identity)->effect);

        $service->reactivateRule(new ReactivateRuleCommand($identity));
        $service->reactivateRule(new ReactivateRuleCommand($identity));
        self::assertSame(RuleLifecycleEnum::ACTIVE, $service->inspectRule($identity)->lifecycle);
        self::assertSame(RuleEffectEnum::DENY, $service->inspectRule($identity)->effect);
    }

    #[Test]
    public function replacementReusesUpdatesReactivatesCreatesAndDeactivatesOnlyItsDimension(): void
    {
        $subject = new Subject('product', '150');
        $otherSubject = new Subject('product', '151');
        $repository = new InMemoryRuleRepository();
        $repository->seed(
            $this->rule('EG', RuleEffectEnum::ALLOW),
            $this->rule('SA', RuleEffectEnum::ALLOW),
            $this->rule('KW', RuleEffectEnum::ALLOW)->withLifecycle(RuleLifecycleEnum::INACTIVE),
            $this->rule('retail', RuleEffectEnum::DENY, 'customer_type'),
            $this->rule('EG', RuleEffectEnum::DENY, 'country', 'product', '151'),
        );
        $service = new EligibilityManagementService($repository, $repository);

        $replacement = new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(
                new DesiredRule('EG', RuleEffectEnum::DENY),
                new DesiredRule('KW', RuleEffectEnum::ALLOW),
                new DesiredRule('QA', RuleEffectEnum::ALLOW),
            ),
        );
        $service->replaceDimensionRules($replacement);
        $service->replaceDimensionRules($replacement);

        $rules = $service->inspectRules(new RuleCriteria($subject));
        self::assertSame(
            [
                ['EG', RuleEffectEnum::DENY, RuleLifecycleEnum::ACTIVE],
                ['KW', RuleEffectEnum::ALLOW, RuleLifecycleEnum::ACTIVE],
                ['QA', RuleEffectEnum::ALLOW, RuleLifecycleEnum::ACTIVE],
                ['SA', RuleEffectEnum::ALLOW, RuleLifecycleEnum::INACTIVE],
                ['retail', RuleEffectEnum::DENY, RuleLifecycleEnum::ACTIVE],
            ],
            array_map(
                static fn (Rule $rule): array => [$rule->dimensionValue, $rule->effect, $rule->lifecycle],
                $rules->items(),
            ),
        );
        self::assertSame(
            RuleEffectEnum::DENY,
            $service->inspectRule(new RuleIdentity('product', '151', 'country', 'EG'))->effect,
        );
        self::assertCount(6, $repository->allRules());
    }

    #[Test]
    public function emptyReplacementDeactivatesActiveRulesAndPreservesInactiveRules(): void
    {
        $subject = new Subject('product', '150');
        $repository = new InMemoryRuleRepository();
        $repository->seed(
            $this->rule('EG', RuleEffectEnum::ALLOW),
            $this->rule('SA', RuleEffectEnum::DENY)->withLifecycle(RuleLifecycleEnum::INACTIVE),
        );
        $service = new EligibilityManagementService($repository, $repository);

        $service->replaceDimensionRules(new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(),
        ));

        self::assertSame(RuleLifecycleEnum::INACTIVE, $service->inspectRule(
            new RuleIdentity('product', '150', 'country', 'EG'),
        )->lifecycle);
        self::assertSame(RuleLifecycleEnum::INACTIVE, $service->inspectRule(
            new RuleIdentity('product', '150', 'country', 'SA'),
        )->lifecycle);
    }

    #[Test]
    public function replacementOwnsTransactionOnlyWhenNoOuterTransactionExists(): void
    {
        $subject = new Subject('product', '150');
        $repository = new InMemoryRuleRepository();
        $service = new EligibilityManagementService($repository, $repository);
        $command = new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(new DesiredRule('EG', RuleEffectEnum::ALLOW)),
        );

        $service->replaceDimensionRules($command);
        self::assertSame(1, $repository->beginCount);
        self::assertSame(1, $repository->commitCount);

        $repository->beginTransaction();
        $service->replaceDimensionRules($command);
        self::assertTrue($repository->inTransaction());
        self::assertSame(2, $repository->beginCount);
        self::assertSame(1, $repository->commitCount);
        self::assertSame(0, $repository->rollbackCount);
        self::assertSame(1, $repository->savepointCreateCount);
        self::assertSame(1, $repository->savepointReleaseCount);
        $repository->commit();
    }

    #[Test]
    public function packageOwnedReplacementRollsBackAndRethrowsOriginalFailure(): void
    {
        $subject = new Subject('product', '150');
        $repository = new InMemoryRuleRepository();
        $repository->seed($this->rule('EG', RuleEffectEnum::ALLOW));
        $service = new EligibilityManagementService($repository, $repository);
        $failure = new \RuntimeException('injected replacement failure');
        $repository->failure = $failure;

        try {
            $service->replaceDimensionRules(new ReplaceDimensionRulesCommand(
                $subject,
                'country',
                new DesiredRuleCollection(new DesiredRule('EG', RuleEffectEnum::DENY)),
            ));
            self::fail('Expected the injected Throwable.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame(RuleEffectEnum::ALLOW, $service->inspectRule(
            new RuleIdentity('product', '150', 'country', 'EG'),
        )->effect);
        self::assertSame(1, $repository->rollbackCount);
    }

    #[Test]
    public function outerTransactionFailureRollsBackOnlyOperationSavepoint(): void
    {
        $subject = new Subject('product', '150');
        $repository = new InMemoryRuleRepository();
        $repository->seed($this->rule('EG', RuleEffectEnum::ALLOW));
        $failure = new \RuntimeException('injected outer replacement failure');
        $service = new EligibilityManagementService(
            new FaultingRuleReplacementRepository($repository, $failure),
            $repository,
        );
        $repository->beginTransaction();

        try {
            $service->replaceDimensionRules(new ReplaceDimensionRulesCommand(
                $subject,
                'country',
                new DesiredRuleCollection(
                    new DesiredRule('EG', RuleEffectEnum::DENY),
                    new DesiredRule('SA', RuleEffectEnum::ALLOW),
                ),
            ));
            self::fail('Expected the injected outer transaction Throwable.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertTrue($repository->inTransaction());
        self::assertSame(RuleEffectEnum::ALLOW, $service->inspectRule(
            new RuleIdentity('product', '150', 'country', 'EG'),
        )->effect);
        self::assertCount(1, $service->inspectRules(new RuleCriteria(
            $subject,
            dimensionKey: 'country',
        )));
        self::assertSame(1, $repository->savepointCreateCount);
        self::assertSame(1, $repository->savepointRollbackCount);
        self::assertSame(1, $repository->savepointReleaseCount);
        $repository->commit();
    }

    #[Test]
    public function savepointRollbackFailureDoesNotReplaceOriginalThrowable(): void
    {
        $subject = new Subject('product', '150');
        $repository = new InMemoryRuleRepository();
        $repository->seed($this->rule('EG', RuleEffectEnum::ALLOW));
        $failure = new \RuntimeException('original outer operation failure');
        $repository->failure = $failure;
        $repository->savepointRollbackFailure = new \RuntimeException('savepoint rollback failure');
        $service = new EligibilityManagementService($repository, $repository);
        $repository->beginTransaction();

        try {
            $service->replaceDimensionRules(new ReplaceDimensionRulesCommand(
                $subject,
                'country',
                new DesiredRuleCollection(new DesiredRule('EG', RuleEffectEnum::DENY)),
            ));
            self::fail('Expected the original outer operation Throwable.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertTrue($repository->inTransaction());
        self::assertSame(0, $repository->savepointRollbackCount);
        $repository->commit();
    }

    #[Test]
    public function cleanupIsIdempotentAndScopedToOneSubject(): void
    {
        $subject = new Subject('product', '150');
        $other = new Subject('product', '151');
        $repository = new InMemoryRuleRepository();
        $repository->seed(
            $this->rule('EG', RuleEffectEnum::ALLOW),
            $this->rule('SA', RuleEffectEnum::DENY)->withLifecycle(RuleLifecycleEnum::INACTIVE),
            $this->rule('EG', RuleEffectEnum::ALLOW, 'country', 'product', '151'),
        );
        $service = new EligibilityManagementService($repository, $repository);

        $service->cleanupSubject(new CleanupSubjectCommand($subject));
        $service->cleanupSubject(new CleanupSubjectCommand($subject));

        self::assertCount(0, $service->inspectRules(new RuleCriteria($subject)));
        self::assertCount(1, $service->inspectRules(new RuleCriteria($other)));
    }

    private function rule(
        string $value,
        RuleEffectEnum $effect,
        string $dimension = 'country',
        string $subjectType = 'product',
        string $subjectId = '150',
    ): Rule {
        return Rule::active(new Subject($subjectType, $subjectId), $dimension, $value, $effect);
    }

    /** @param callable(): void $mutation */
    private function assertMissingMutationProducesTypedNotFound(callable $mutation, RuleIdentity $identity): void
    {
        try {
            $mutation();
            self::fail('Expected a missing Rule mutation to throw RuleNotFoundException.');
        } catch (RuleNotFoundException $exception) {
            self::assertSame($identity, $exception->identity());
        }
    }
}
