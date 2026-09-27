<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Integration;

use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\Evaluation\Enum\DecisionReasonEnum;
use Maatify\Eligibility\Evaluation\ValueObject\Context;
use Maatify\Eligibility\Evaluation\ValueObject\ContextDimension;
use Maatify\Eligibility\Factory\Pdo\PdoEligibilityRuntimeFactory;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\ValueObject\DesiredRule;
use Maatify\Eligibility\Management\ValueObject\DesiredRuleCollection;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\ValueObject\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PDO;

final class PdoEligibilityRuntimeFactoryIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = IntegrationDatabase::connect();
        IntegrationDatabase::applySchema($this->pdo);
        IntegrationDatabase::clearRules($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        IntegrationDatabase::clearRules($this->pdo);
    }

    #[Test]
    public function factoryBuildsTheDefaultManagementAndEvaluationWorkflow(): void
    {
        $factory = new PdoEligibilityRuntimeFactory($this->pdo);
        $management = $factory->createManagementService();
        $evaluation = $factory->createEvaluationService();
        $subject = new Subject('product', 'factory-workflow');

        $management->cleanupSubject(new CleanupSubjectCommand($subject));
        $created = $management->createRule(new CreateRuleCommand(
            $subject,
            'country',
            'EG',
            RuleEffectEnum::ALLOW,
        ));

        $inspected = $management->inspectRule($created->naturalIdentity());
        self::assertSame($subject->subjectType, $inspected->subject->subjectType);
        self::assertSame($subject->subjectId, $inspected->subject->subjectId);
        self::assertSame(
            DecisionReasonEnum::ELIGIBLE,
            $evaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'EG')),
            )->reasonCode,
        );
    }

    #[Test]
    public function factoryPreservesCallerOwnedOuterTransactionAndEvaluationVisibility(): void
    {
        $factory = new PdoEligibilityRuntimeFactory($this->pdo);
        $management = $factory->createManagementService();
        $evaluation = $factory->createEvaluationService();
        $subject = new Subject('product', 'factory-transaction');

        $management->cleanupSubject(new CleanupSubjectCommand($subject));
        $this->pdo->beginTransaction();
        $management->replaceDimensionRules(new ReplaceDimensionRulesCommand(
            $subject,
            'country',
            new DesiredRuleCollection(new DesiredRule('EG', RuleEffectEnum::ALLOW)),
        ));

        self::assertTrue($this->pdo->inTransaction());
        self::assertSame(
            DecisionReasonEnum::ELIGIBLE,
            $evaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'EG')),
            )->reasonCode,
        );

        $this->pdo->rollBack();

        self::assertSame(
            DecisionReasonEnum::UNRESTRICTED,
            $evaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'EG')),
            )->reasonCode,
        );
    }
}
