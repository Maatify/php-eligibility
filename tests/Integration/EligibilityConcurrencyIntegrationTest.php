<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Integration;

use Maatify\Eligibility\Application\Command\CreateRuleCommand;
use Maatify\Eligibility\Application\Command\DesiredRule;
use Maatify\Eligibility\Application\Command\DesiredRuleCollection;
use Maatify\Eligibility\Application\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Application\Query\RuleCriteria;
use Maatify\Eligibility\Application\Service\EligibilityManagementService;
use Maatify\Eligibility\Rule\Repository\PdoRuleRepository;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\Value\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PDO;

final class EligibilityConcurrencyIntegrationTest extends TestCase
{
    private PDO $pdo;

    private PdoRuleRepository $repository;

    private EligibilityManagementService $management;

    protected function setUp(): void
    {
        $this->pdo = IntegrationDatabase::connect();
        IntegrationDatabase::applySchema($this->pdo);
        IntegrationDatabase::clearRules($this->pdo);
        $this->repository = new PdoRuleRepository($this->pdo);
        $this->management = new EligibilityManagementService($this->repository);
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
    public function concurrentCreateHasOneWinnerAndTypedDuplicateOutcome(): void
    {
        $workerA = null;
        $workerB = null;
        try {
            $workerA = $this->startWorker([
                'hold-create',
                'product',
                '150',
                'country',
                'EG',
                RuleEffectEnum::ALLOW->value,
            ]);
            self::assertSame('CREATED', $this->readLine($workerA['stdout']));

            $workerB = $this->startWorker([
                'create',
                'product',
                '150',
                'country',
                'EG',
                RuleEffectEnum::DENY->value,
            ]);
            self::assertSame('STARTED', $this->readLine($workerB['stdout']));

            $this->signal($workerA['stdin'], 'COMMIT');
            self::assertSame('DONE', $this->readLine($workerA['stdout']));
            self::assertSame('RESULT:DUPLICATE', $this->readLine($workerB['stdout']));
        } finally {
            $this->closeWorker($workerA);
            $this->closeWorker($workerB);
        }

        self::assertSame(1, $this->countAllRules());
        self::assertSame(RuleEffectEnum::ALLOW, $this->repository->findByIdentity(
            new \Maatify\Eligibility\Rule\RuleIdentity('product', '150', 'country', 'EG'),
        )?->effect);
    }

    #[Test]
    public function concurrentReplacementOnInitiallyEmptyDimensionSerializesWholeState(): void
    {
        $subject = new Subject('product', '150');
        $workerA = null;
        $workerB = null;
        $observer = IntegrationDatabase::connect();
        try {
            $workerA = $this->startWorker([
                'hold-replace',
                $subject->subjectType,
                $subject->subjectId,
                'country',
                $this->encodeDesired([
                    ['EG', RuleEffectEnum::ALLOW->value],
                ]),
            ]);
            self::assertSame('LOCKED', $this->readLine($workerA['stdout']));

            $workerB = $this->startWorker([
                'replace',
                $subject->subjectType,
                $subject->subjectId,
                'country',
                $this->encodeDesired([
                    ['SA', RuleEffectEnum::DENY->value],
                    ['KW', RuleEffectEnum::ALLOW->value],
                ]),
            ]);
            self::assertSame('STARTED', $this->readLine($workerB['stdout']));
            self::assertSame('ATTEMPTING_LOCK', $this->readLine($workerB['stdout']));

            $this->signal($workerA['stdin'], 'REPLACE');
            self::assertSame('REPLACED', $this->readLine($workerA['stdout']));

            $observerRepository = new PdoRuleRepository($observer);
            self::assertCount(0, $observerRepository->findByCriteria(new RuleCriteria($subject)));

            $this->signal($workerA['stdin'], 'COMMIT');
            self::assertSame('DONE', $this->readLine($workerA['stdout']));
            self::assertSame('DONE', $this->readLine($workerB['stdout']));
        } finally {
            $this->closeWorker($workerA);
            $this->closeWorker($workerB);
            if ($observer->inTransaction()) {
                $observer->rollBack();
            }
        }

        $rules = $this->repository->findByCriteria(new RuleCriteria($subject, lifecycle: \Maatify\Eligibility\Rule\RuleLifecycleEnum::ACTIVE));
        self::assertSame(
            [
                ['KW', RuleEffectEnum::ALLOW],
                ['SA', RuleEffectEnum::DENY],
            ],
            array_map(
                static fn (\Maatify\Eligibility\Rule\Rule $rule): array => [$rule->dimensionValue, $rule->effect],
                $rules->items(),
            ),
        );
    }

    #[Test]
    public function concurrentReplacementOnExistingDimensionDoesNotMixDesiredSets(): void
    {
        $subject = new Subject('product', '150');
        $this->management->replaceDimensionRules($this->replacement(
            $subject,
            new DesiredRule('OLD', RuleEffectEnum::ALLOW),
        ));

        $workerA = null;
        $workerB = null;
        try {
            $workerA = $this->startWorker([
                'hold-replace',
                $subject->subjectType,
                $subject->subjectId,
                'country',
                $this->encodeDesired([
                    ['A', RuleEffectEnum::ALLOW->value],
                    ['A2', RuleEffectEnum::DENY->value],
                ]),
            ]);
            self::assertSame('LOCKED', $this->readLine($workerA['stdout']));

            $workerB = $this->startWorker([
                'replace',
                $subject->subjectType,
                $subject->subjectId,
                'country',
                $this->encodeDesired([
                    ['B', RuleEffectEnum::ALLOW->value],
                    ['B2', RuleEffectEnum::DENY->value],
                ]),
            ]);
            self::assertSame('STARTED', $this->readLine($workerB['stdout']));
            self::assertSame('ATTEMPTING_LOCK', $this->readLine($workerB['stdout']));

            $this->signal($workerA['stdin'], 'REPLACE');
            self::assertSame('REPLACED', $this->readLine($workerA['stdout']));
            $this->signal($workerA['stdin'], 'COMMIT');
            self::assertSame('DONE', $this->readLine($workerA['stdout']));
            self::assertSame('DONE', $this->readLine($workerB['stdout']));
        } finally {
            $this->closeWorker($workerA);
            $this->closeWorker($workerB);
        }

        $rules = $this->repository->findByCriteria(new RuleCriteria($subject, lifecycle: \Maatify\Eligibility\Rule\RuleLifecycleEnum::ACTIVE));
        self::assertSame(['B', 'B2'], array_map(
            static fn (\Maatify\Eligibility\Rule\Rule $rule): string => $rule->dimensionValue,
            $rules->items(),
        ));
        self::assertSame(5, $this->countAllRules());
    }

    /** @param list<array{0: string, 1: string}> $desired */
    private function encodeDesired(array $desired): string
    {
        return json_encode($desired, JSON_THROW_ON_ERROR);
    }

    private function replacement(Subject $subject, DesiredRule ...$desired): ReplaceDimensionRulesCommand
    {
        return new ReplaceDimensionRulesCommand($subject, 'country', new DesiredRuleCollection(...$desired));
    }

    private function countAllRules(): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM `maa_eligibility_rules`');
        if ($statement === false) {
            self::fail('Could not count Eligibility Rules.');
        }

        return (int) $statement->fetchColumn();
    }

    /**
     * @param list<string> $arguments
     * @return array{process: resource, stdin: resource, stdout: resource, stderr: resource}
     */
    private function startWorker(array $arguments): array
    {
        $command = array_merge([
            PHP_BINARY,
            __DIR__ . '/../Support/ConcurrencyWorker.php',
        ], $arguments);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));
        if (!is_resource($process) || !isset($pipes[0], $pipes[1], $pipes[2])) {
            self::fail('Could not start the concurrency worker.');
        }

        /** @var resource $stdin */
        $stdin = $pipes[0];
        /** @var resource $stdout */
        $stdout = $pipes[1];
        /** @var resource $stderr */
        $stderr = $pipes[2];

        return [
            'process' => $process,
            'stdin' => $stdin,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /** @param resource $pipe */
    private function readLine($pipe): string
    {
        $line = fgets($pipe);
        if ($line === false) {
            self::fail('Concurrency worker closed its output unexpectedly.');
        }

        return trim($line);
    }

    /** @param resource $pipe */
    private function signal($pipe, string $signal): void
    {
        if (fwrite($pipe, $signal . PHP_EOL) === false) {
            self::fail('Could not signal concurrency worker.');
        }
        fflush($pipe);
    }

    /**
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource}|null $worker
     */
    private function closeWorker(?array $worker): void
    {
        if ($worker === null) {
            return;
        }

        fclose($worker['stdin']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exitCode = proc_close($worker['process']);
        self::assertSame(0, $exitCode);
    }
}
