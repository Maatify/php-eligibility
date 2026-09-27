<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\ValueObject\DesiredRule;
use Maatify\Eligibility\Management\ValueObject\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Criteria\ActiveDimensionKeysCriteria;
use Maatify\Eligibility\Management\Criteria\RuleCriteria;
use Maatify\Eligibility\Management\Criteria\RuleLifecycleSummaryCriteria;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\ValueObject\Rule;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Eligibility\Enum\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\InMemoryRuleRepository;
use Maatify\Eligibility\Tests\Support\SynchronousTransactionRunner;
use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Persistence\Pdo\Pagination\PageRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuntimeManagementServiceTest extends TestCase
{
    #[Test]
    public function createInspectReadsAndActiveDimensionKeysDelegateTypedResults(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = $this->service($repository);
        $command = new CreateRuleCommand(new Subject('product', '150'), 'country', 'EG', RuleEffectEnum::ALLOW);

        $created = $service->createRule($command);
        $inspected = $service->inspectRule($created->naturalIdentity());

        self::assertSame(RuleLifecycleEnum::ACTIVE, $created->lifecycle);
        self::assertSame($created->jsonSerialize(), $inspected->jsonSerialize());
        $activeDimensionKeys = $service->inspectActiveDimensionKeys(
            new ActiveDimensionKeysCriteria($command->subject),
            new PageRequest(),
        );
        self::assertSame(['country'], array_map(
            static fn($dto): string => $dto->dimensionKey,
            $activeDimensionKeys->data,
        ));
        self::assertSame(1, $activeDimensionKeys->total);
        self::assertSame(1, $activeDimensionKeys->filtered);
    }

    #[Test]
    public function inspectMissingAndMissingMutationsProduceTypedNotFound(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = $this->service($repository);
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
        $service = $this->service($repository);

        $all = $service->inspectRules(new RuleCriteria($subject), new PageRequest());
        self::assertCount(2, $all->data);
        self::assertSame(2, $all->total);
        self::assertSame(2, $all->filtered);

        $inactiveOnly = $service->inspectRules(new RuleCriteria(
            $subject,
            lifecycle: RuleLifecycleEnum::INACTIVE,
        ), new PageRequest());
        self::assertCount(1, $inactiveOnly->data);
        self::assertSame(2, $inactiveOnly->total);
        self::assertSame(1, $inactiveOnly->filtered);

        $denyOnly = $service->inspectRules(new RuleCriteria(
            $subject,
            effect: RuleEffectEnum::DENY,
        ), new PageRequest());
        self::assertCount(1, $denyOnly->data);
        self::assertSame('SA', $denyOnly->data[0]->dimensionValue);
    }

    #[Test]
    public function inspectRulesRejectsUnsupportedExplicitSortRequests(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = $this->service($repository);
        $subject = new Subject('product', '150');

        $this->expectException(InvalidEligibilityInputException::class);

        $service->inspectRules(
            new RuleCriteria($subject),
            new PageRequest(sortBy: 'dimension_value', sortDirection: 'ASC'),
        );
    }

    #[Test]
    public function inspectRulesAcceptsTheCanonicalExplicitSortEquivalent(): void
    {
        $repository = new InMemoryRuleRepository();
        $repository->seed($this->rule('EG', RuleEffectEnum::ALLOW));
        $service = $this->service($repository);
        $subject = new Subject('product', '150');

        $result = $service->inspectRules(
            new RuleCriteria($subject),
            new PageRequest(sortBy: 'dimension_key', sortDirection: 'ASC'),
        );

        self::assertCount(1, $result->data);
    }

    #[Test]
    public function inspectRuleLifecycleSummaryReportsCountsForTheSubject(): void
    {
        $repository = new InMemoryRuleRepository();
        $subject = new Subject('product', '150');
        $repository->seed(
            $this->rule('EG', RuleEffectEnum::ALLOW),
            $this->rule('SA', RuleEffectEnum::DENY)->withLifecycle(RuleLifecycleEnum::INACTIVE),
            $this->rule('retail', RuleEffectEnum::ALLOW, 'customer_type'),
        );
        $service = $this->service($repository);

        $summary = $service->inspectRuleLifecycleSummary(new RuleLifecycleSummaryCriteria($subject));
        self::assertSame(3, $summary->totalRules);
        self::assertSame(2, $summary->activeRules);
        self::assertSame(1, $summary->inactiveRules);

        $dimensionSummary = $service->inspectRuleLifecycleSummary(
            new RuleLifecycleSummaryCriteria($subject, 'country'),
        );
        self::assertSame(2, $dimensionSummary->totalRules);
        self::assertSame(1, $dimensionSummary->activeRules);
        self::assertSame(1, $dimensionSummary->inactiveRules);

        $emptySummary = $service->inspectRuleLifecycleSummary(
            new RuleLifecycleSummaryCriteria(new Subject('product', 'missing')),
        );
        self::assertSame(0, $emptySummary->totalRules);
        self::assertSame(0, $emptySummary->activeRules);
        self::assertSame(0, $emptySummary->inactiveRules);
    }

    #[Test]
    public function lifecycleAndEffectCommandsAreOrthogonalAndIdempotent(): void
    {
        $repository = new InMemoryRuleRepository();
        $service = $this->service($repository);
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
        $service = $this->service($repository);

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

        $rules = $service->inspectRules(new RuleCriteria($subject), new PageRequest());
        self::assertSame(
            [
                ['EG', RuleEffectEnum::DENY, RuleLifecycleEnum::ACTIVE],
                ['KW', RuleEffectEnum::ALLOW, RuleLifecycleEnum::ACTIVE],
                ['QA', RuleEffectEnum::ALLOW, RuleLifecycleEnum::ACTIVE],
                ['SA', RuleEffectEnum::ALLOW, RuleLifecycleEnum::INACTIVE],
                ['retail', RuleEffectEnum::DENY, RuleLifecycleEnum::ACTIVE],
            ],
            array_map(
                static fn(Rule $rule): array => [$rule->dimensionValue, $rule->effect, $rule->lifecycle],
                $rules->data,
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
        $service = $this->service($repository);

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
    public function replacementAndCleanupDelegateToTheSharedTransactionRunner(): void
    {
        $subject = new Subject('product', '150');
        $repository = new InMemoryRuleRepository();
        $runner = new SynchronousTransactionRunner();
        $service = new EligibilityManagementService($repository, $repository, $repository, $runner);
        $command = new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(new DesiredRule('EG', RuleEffectEnum::ALLOW)),
        );

        $service->replaceDimensionRules($command);
        $service->cleanupSubject(new CleanupSubjectCommand($subject));

        self::assertSame(2, $runner->runCount);
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
        $service = $this->service($repository);

        $service->cleanupSubject(new CleanupSubjectCommand($subject));
        $service->cleanupSubject(new CleanupSubjectCommand($subject));

        self::assertCount(0, $service->inspectRules(new RuleCriteria($subject), new PageRequest())->data);
        self::assertCount(1, $service->inspectRules(new RuleCriteria($other), new PageRequest())->data);
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

    private function service(InMemoryRuleRepository $repository): EligibilityManagementService
    {
        return new EligibilityManagementService(
            $repository,
            $repository,
            $repository,
            new SynchronousTransactionRunner(),
        );
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
