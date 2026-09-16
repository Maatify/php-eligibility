<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Integration;

use Maatify\Eligibility\Application\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DesiredRule;
use Maatify\Eligibility\Application\Command\DesiredRuleCollection;
use Maatify\Eligibility\Application\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Application\Service\EligibilityManagementService;
use Maatify\Eligibility\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Exception\RuleNotFoundException;
use Maatify\Eligibility\Rule\Repository\PdoRuleRepository;
use Maatify\Eligibility\Rule\Rule;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\FaultingRuleReplacementRepository;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\Value\Context;
use Maatify\Eligibility\Value\ContextDimension;
use Maatify\Eligibility\Value\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PDO;

final class EligibilityRuntimeIntegrationTest extends TestCase
{
    private PDO $pdo;

    private PdoRuleRepository $repository;

    private EligibilityManagementService $management;

    private EligibilityEvaluationService $evaluation;

    protected function setUp(): void
    {
        $this->pdo = IntegrationDatabase::connect();
        IntegrationDatabase::applySchema($this->pdo);
        IntegrationDatabase::clearRules($this->pdo);
        $this->repository = new PdoRuleRepository($this->pdo);
        $this->management = new EligibilityManagementService($this->repository);
        $this->evaluation = new EligibilityEvaluationService($this->repository);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        IntegrationDatabase::clearRules($this->pdo);
        self::assertFalse($this->pdo->inTransaction());
    }

    #[Test]
    public function servicesEvaluateAndManageTheRealRuleBoundary(): void
    {
        $subject = new Subject('product', '150');
        $this->management->createRule(new CreateRuleCommand(
            $subject,
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        ));
        $this->management->createRule(new CreateRuleCommand(
            $subject,
            'customer_type',
            'blocked',
            RuleEffectEnum::DENY,
        ));

        $decision = $this->evaluation->decide($subject, new Context(
            ContextDimension::fromStrings('country', 'EG'),
            ContextDimension::fromStrings('customer_type', 'retail'),
        ));

        self::assertSame(DecisionReasonEnum::ELIGIBLE, $decision->reasonCode);
        self::assertSame(['country', 'customer_type'], array_map(
            static fn (\Maatify\Eligibility\Decision\DimensionOutcome $outcome): string => $outcome->dimensionKey,
            $decision->dimensionOutcomes->items(),
        ));

        $this->management->deactivateRule(new \Maatify\Eligibility\Application\Command\DeactivateRuleCommand(
            new RuleIdentity('product', '150', 'country', 'EG'),
        ));
        self::assertCount(2, $this->management->inspectRules(new RuleCriteria($subject)));
        self::assertSame(DecisionReasonEnum::ELIGIBLE, $this->evaluation->decide(
            $subject,
            new Context(ContextDimension::fromStrings('customer_type', 'retail')),
        )->reasonCode);
    }

    #[Test]
    public function replacementUsesCompleteUnboundedDimensionStateAndIsIdempotent(): void
    {
        $subject = new Subject('bulk', '500');
        $otherSubject = new Subject('bulk', '501');
        $otherDimension = 'customer_type';

        for ($index = 0; $index <= 500; $index++) {
            $value = sprintf('v%03d', $index);
            $this->repository->create(new CreateRuleCommand(
                $subject,
                'country',
                $value,
                RuleEffectEnum::ALLOW,
            ));
        }
        $this->repository->create(new CreateRuleCommand(
            $subject,
            $otherDimension,
            'retail',
            RuleEffectEnum::ALLOW,
        ));
        $this->repository->create(new CreateRuleCommand(
            $otherSubject,
            'country',
            'EG',
            RuleEffectEnum::DENY,
        ));

        $replacement = new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(
                new DesiredRule('v000', RuleEffectEnum::DENY),
                new DesiredRule('new', RuleEffectEnum::ALLOW),
            ),
        );
        $this->management->replaceDimensionRules($replacement);
        $this->management->replaceDimensionRules($replacement);

        self::assertSame(['total' => 502, 'active' => 2, 'inactive' => 500], $this->stateCounts($subject, 'country'));
        self::assertSame(['total' => 1, 'active' => 1, 'inactive' => 0], $this->stateCounts($subject, $otherDimension));
        self::assertSame(['total' => 1, 'active' => 1, 'inactive' => 0], $this->stateCounts($otherSubject, 'country'));
        self::assertSame(RuleLifecycleEnum::INACTIVE, $this->repository->findByIdentity(
            new RuleIdentity('bulk', '500', 'country', 'v500'),
        )?->lifecycle);
        self::assertSame(RuleEffectEnum::DENY, $this->repository->findByIdentity(
            new RuleIdentity('bulk', '500', 'country', 'v000'),
        )?->effect);
    }

    #[Test]
    public function packageAndHostTransactionOwnershipControlDurability(): void
    {
        $subject = new Subject('product', '150');
        $otherConnection = IntegrationDatabase::connect();
        try {
            $command = $this->replacement($subject, new DesiredRule('EG', RuleEffectEnum::ALLOW));
            $this->management->replaceDimensionRules($command);
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(['total' => 1, 'active' => 1, 'inactive' => 0], $this->stateCounts($subject, 'country'));

            $this->pdo->beginTransaction();
            $this->management->replaceDimensionRules($this->replacement(
                $subject,
                new DesiredRule('SA', RuleEffectEnum::DENY),
            ));
            self::assertTrue($this->pdo->inTransaction());
            self::assertSame(1, $this->countRows($otherConnection, $subject, 'country', 'EG', 'active'));
            self::assertSame(0, $this->countRows($otherConnection, $subject, 'country', 'SA', 'active'));
            $this->pdo->commit();
            self::assertSame(1, $this->countRows($otherConnection, $subject, 'country', 'SA', 'active'));

            $this->pdo->beginTransaction();
            $this->management->replaceDimensionRules($this->replacement(
                $subject,
                new DesiredRule('KW', RuleEffectEnum::ALLOW),
            ));
            self::assertSame(1, $this->countRows($this->pdo, $subject, 'country', 'KW', 'active'));
            self::assertSame(0, $this->countRows($otherConnection, $subject, 'country', 'KW', 'active'));
            $this->pdo->rollBack();
            self::assertSame(0, $this->countRows($otherConnection, $subject, 'country', 'KW', 'active'));
            self::assertSame(1, $this->countRows($otherConnection, $subject, 'country', 'SA', 'active'));
        } finally {
            if ($otherConnection->inTransaction()) {
                $otherConnection->rollBack();
            }
        }
    }

    #[Test]
    public function realBoundaryFailureRollsBackTheCompleteReplacementAndKeepsOriginalThrowable(): void
    {
        $subject = new Subject('product', '150');
        $this->repository->create(new CreateRuleCommand(
            $subject,
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        ));
        $failure = new \RuntimeException('real-boundary injected failure');
        $faultingRepository = new FaultingRuleReplacementRepository($this->repository, $failure);
        $service = new EligibilityManagementService($faultingRepository);

        try {
            $service->replaceDimensionRules(new ReplaceDimensionRulesCommand(
                $subject,
                'country',
                new DesiredRuleCollection(
                    new DesiredRule('EG', RuleEffectEnum::DENY),
                    new DesiredRule('SA', RuleEffectEnum::ALLOW),
                ),
            ));
            self::fail('Expected the injected real-boundary failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        $eg = $this->repository->findByIdentity(new RuleIdentity('product', '150', 'country', 'EG'));
        self::assertNotNull($eg);
        self::assertSame(RuleEffectEnum::ALLOW, $eg->effect);
        self::assertNull($this->repository->findByIdentity(new RuleIdentity('product', '150', 'country', 'SA')));
        self::assertFalse($this->pdo->inTransaction());
    }

    #[Test]
    public function outerHostFailureRollsBackOnlyReplacementAndPreservesHostWork(): void
    {
        $subject = new Subject('product', '150');
        $otherConnection = IntegrationDatabase::connect();
        $this->repository->create(new CreateRuleCommand(
            $subject,
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        ));
        $failure = new \RuntimeException('real outer-boundary injected failure');
        $service = new EligibilityManagementService(new FaultingRuleReplacementRepository(
            $this->repository,
            $failure,
        ));

        try {
            $this->pdo->beginTransaction();
            $this->insertHostPriorWork();

            try {
                $service->replaceDimensionRules($this->replacement(
                    $subject,
                    new DesiredRule('EG', RuleEffectEnum::DENY),
                    new DesiredRule('SA', RuleEffectEnum::ALLOW),
                ));
                self::fail('Expected the injected outer-boundary failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame($failure, $exception);
            }

            self::assertTrue($this->pdo->inTransaction());
            self::assertSame(['total' => 1, 'active' => 1, 'inactive' => 0], $this->stateCounts($subject, 'country'));
            self::assertSame(1, $this->countRows($this->pdo, $subject, 'country', 'EG', 'active'));
            self::assertSame(0, $this->countRows($this->pdo, $subject, 'country', 'SA', 'active'));
            self::assertSame(1, $this->countHostPriorWork($this->pdo));

            $this->pdo->commit();
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(1, $this->countHostPriorWork($otherConnection));
            self::assertSame(1, $this->countRows($otherConnection, $subject, 'country', 'EG', 'active'));
            self::assertSame(0, $this->countRows($otherConnection, $subject, 'country', 'SA', 'active'));
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($otherConnection->inTransaction()) {
                $otherConnection->rollBack();
            }
        }
    }

    #[Test]
    public function outerCleanupFailureRollsBackRulesAndCoordinationToSavepoint(): void
    {
        $subject = new Subject('product', '150');
        $this->management->replaceDimensionRules($this->replacement(
            $subject,
            new DesiredRule('EG', RuleEffectEnum::ALLOW),
        ));
        self::assertSame(1, $this->countCoordinationRows($subject));

        $failure = new \RuntimeException('real cleanup coordination failure');
        $service = new EligibilityManagementService(new FaultingRuleReplacementRepository(
            $this->repository,
            new \RuntimeException('unused create failure'),
            $failure,
        ));
        $this->pdo->beginTransaction();

        try {
            $service->cleanupSubject(new CleanupSubjectCommand($subject));
            self::fail('Expected the injected cleanup failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertTrue($this->pdo->inTransaction());
        self::assertSame(['total' => 1, 'active' => 1, 'inactive' => 0], $this->stateCounts($subject, 'country'));
        self::assertSame(1, $this->countCoordinationRows($subject));
        $this->pdo->commit();
        self::assertFalse($this->pdo->inTransaction());
        self::assertSame(1, $this->countCoordinationRows($subject));
    }

    #[Test]
    public function managementCleanupPhysicallyRemovesRulesAndCoordinationRows(): void
    {
        $subject = new Subject('product', '150');
        $this->management->replaceDimensionRules($this->replacement(
            $subject,
            new DesiredRule('EG', RuleEffectEnum::ALLOW),
        ));
        $this->management->cleanupSubject(new CleanupSubjectCommand($subject));
        $this->management->cleanupSubject(new CleanupSubjectCommand($subject));

        self::assertCount(0, $this->management->inspectRules(new RuleCriteria($subject)));
        self::assertSame(0, $this->countCoordinationRows($subject));
    }

    private function replacement(Subject $subject, DesiredRule ...$desiredRules): ReplaceDimensionRulesCommand
    {
        return new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(...$desiredRules),
        );
    }

    /** @return array{total: int, active: int, inactive: int} */
    private function stateCounts(Subject $subject, string $dimension): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS `total`, '
            . 'SUM(CASE WHEN `lifecycle` = ? THEN 1 ELSE 0 END) AS `active`, '
            . 'SUM(CASE WHEN `lifecycle` = ? THEN 1 ELSE 0 END) AS `inactive` '
            . 'FROM `maa_eligibility_rules` WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ?',
        );
        $statement->execute([
            RuleLifecycleEnum::ACTIVE->value,
            RuleLifecycleEnum::INACTIVE->value,
            $subject->subjectType,
            $subject->subjectId,
            $dimension,
        ]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            self::fail('Could not read replacement state counts.');
        }

        return [
            'total' => $this->integerColumn($row, 'total'),
            'active' => $this->integerColumn($row, 'active'),
            'inactive' => $this->integerColumn($row, 'inactive'),
        ];
    }

    /** @param array<string, mixed> $row */
    private function integerColumn(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        self::fail(sprintf('Expected numeric column %s.', $column));
    }

    private function countRows(PDO $pdo, Subject $subject, string $dimension, string $value, string $lifecycle): int
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM `maa_eligibility_rules` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? '
            . 'AND `dimension_value` = ? AND `lifecycle` = ?',
        );
        $statement->execute([
            $subject->subjectType,
            $subject->subjectId,
            $dimension,
            $value,
            $lifecycle,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function insertHostPriorWork(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO `maa_eligibility_rules` '
            . '(`subject_type`, `subject_id`, `dimension_key`, `dimension_value`, `effect`, `lifecycle`) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
        );
        $statement->execute(['host', 'prior', 'host_probe', 'write', 'allow', 'active']);
    }

    private function countHostPriorWork(PDO $pdo): int
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM `maa_eligibility_rules` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ? AND `dimension_key` = ? AND `dimension_value` = ?',
        );
        $statement->execute(['host', 'prior', 'host_probe', 'write']);

        return (int) $statement->fetchColumn();
    }

    private function countCoordinationRows(Subject $subject, ?PDO $pdo = null): int
    {
        $connection = $pdo ?? $this->pdo;
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM `maa_eligibility_subject_locks` '
            . 'WHERE `subject_type` = ? AND `subject_id` = ?',
        );
        $statement->execute([$subject->subjectType, $subject->subjectId]);

        return (int) $statement->fetchColumn();
    }
}
