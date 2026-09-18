<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Integration;

use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Evaluation\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Rule\Repository\PdoActiveRuleReader;
use Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository;
use Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Tests\Support\ConcurrencyTimeout;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\Evaluation\Value\Context;
use Maatify\Eligibility\Evaluation\Value\ContextDimension;
use Maatify\Eligibility\Common\Value\Subject;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;
use PDO;

final class EligibilityConcurrencyIntegrationTest extends TestCase
{
    private PDO $pdo;

    private PdoRuleCommandRepository $repository;

    private PdoRuleManagementQuery $managementQuery;

    private PdoSavepointTransactionRunner $transactionRunner;

    private EligibilityManagementService $management;

    /** @var array<int, string> */
    private array $stdoutBuffers = [];

    protected function setUp(): void
    {
        $this->pdo = IntegrationDatabase::connect();
        IntegrationDatabase::applySchema($this->pdo);
        IntegrationDatabase::clearRules($this->pdo);
        $this->repository = new PdoRuleCommandRepository($this->pdo);
        $this->managementQuery = new PdoRuleManagementQuery($this->pdo);
        $this->transactionRunner = new PdoSavepointTransactionRunner($this->pdo);
        $this->management = new EligibilityManagementService(
            $this->repository,
            $this->managementQuery,
            $this->repository,
            $this->transactionRunner,
        );
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
    public function missingWorkerOutputFailsWithinTheHarnessDeadline(): void
    {
        $worker = null;
        try {
            $worker = $this->startWorker(['silent']);
            $startedAt = hrtime(true);

            try {
                $this->readLine($worker, 'silent worker output');
                self::fail('Expected the silent worker output wait to time out.');
            } catch (AssertionFailedError $exception) {
                self::assertStringContainsString(
                    'Timed out waiting for silent worker output',
                    $exception->getMessage(),
                );
                self::assertLessThan(
                    (ConcurrencyTimeout::SECONDS + 1) * 1_000_000_000,
                    hrtime(true) - $startedAt,
                );
            }
        } finally {
            $this->closeWorkers($worker, null);
        }
    }

    #[Test]
    public function workerSignalWaitFailsBoundedWithDiagnosticAndNonZeroExit(): void
    {
        $worker = null;
        $primaryFailure = null;
        try {
            $worker = $this->startWorker(['await-signal']);
            $status = $this->waitForWorker($worker['process'], ConcurrencyTimeout::observationDeadline());

            self::assertFalse($status['running']);
            self::assertNotSame(0, $status['exitcode']);
            self::assertSame(
                'ERROR:RuntimeException:Concurrency worker timed out waiting for its signal after 2 seconds.',
                $this->readLine($worker, 'worker signal timeout diagnostic'),
            );
        } catch (\Throwable $exception) {
            $primaryFailure = $exception;
            throw $exception;
        } finally {
            $this->closeWorkers($worker, null, $primaryFailure !== null, true);
        }
    }

    #[Test]
    public function longLivedWorkerCleanupIsBounded(): void
    {
        $worker = $this->startWorker(['silent']);
        $startedAt = hrtime(true);

        $this->closeWorkers($worker, null);

        self::assertLessThan(
            (ConcurrencyTimeout::SECONDS * 2 + 1) * 1_000_000_000,
            hrtime(true) - $startedAt,
        );
    }

    #[Test]
    public function concurrentCreateHasOneWinnerAndTypedDuplicateOutcome(): void
    {
        $workerA = null;
        $workerB = null;
        $primaryFailure = null;
        try {
            $workerA = $this->startWorker([
                'hold-create',
                'product',
                '150',
                'country',
                'EG',
                RuleEffectEnum::ALLOW->value,
            ]);
            self::assertSame('CREATED', $this->readLine($workerA, 'worker A create result'));

            $workerB = $this->startWorker([
                'create',
                'product',
                '150',
                'country',
                'EG',
                RuleEffectEnum::DENY->value,
            ]);
            self::assertSame('STARTED', $this->readLine($workerB, 'worker B start event'));

            $this->signal($workerA, 'COMMIT');
            self::assertSame('DONE', $this->readLine($workerA, 'worker A commit result'));
            self::assertSame('RESULT:DUPLICATE', $this->readLine($workerB, 'worker B duplicate result'));
        } catch (\Throwable $exception) {
            $primaryFailure = $exception;
            throw $exception;
        } finally {
            $this->closeWorkers($workerA, $workerB, $primaryFailure !== null);
        }

        self::assertSame(1, $this->countAllRules());
        self::assertSame(RuleEffectEnum::ALLOW, $this->managementQuery->findByIdentity(
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
        $primaryFailure = null;
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
            self::assertSame('LOCKED', $this->readLine($workerA, 'worker A subject lock'));

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
            self::assertSame('STARTED', $this->readLine($workerB, 'worker B start event'));
            self::assertSame('ATTEMPTING_LOCK', $this->readLine($workerB, 'worker B subject lock attempt'));

            $this->signal($workerA, 'REPLACE');
            self::assertSame('REPLACED', $this->readLine($workerA, 'worker A replacement result'));

            $observerQuery = new PdoRuleManagementQuery($observer);
            self::assertCount(0, $observerQuery->findByCriteria(new RuleCriteria($subject)));

            $this->signal($workerA, 'COMMIT');
            self::assertSame('DONE', $this->readLine($workerA, 'worker A commit result'));
            self::assertSame('DONE', $this->readLine($workerB, 'worker B replacement result'));
        } catch (\Throwable $exception) {
            $primaryFailure = $exception;
            throw $exception;
        } finally {
            try {
                $this->closeWorkers($workerA, $workerB, $primaryFailure !== null);
            } finally {
                if ($observer->inTransaction()) {
                    $observer->rollBack();
                }
            }
        }

        $rules = $this->managementQuery->findByCriteria(new RuleCriteria($subject, lifecycle: \Maatify\Eligibility\Rule\RuleLifecycleEnum::ACTIVE));
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
        $observer = IntegrationDatabase::connect();
        $primaryFailure = null;
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
            self::assertSame('LOCKED', $this->readLine($workerA, 'worker A subject lock'));

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
            self::assertSame('STARTED', $this->readLine($workerB, 'worker B start event'));
            self::assertSame('ATTEMPTING_LOCK', $this->readLine($workerB, 'worker B subject lock attempt'));

            $this->signal($workerA, 'REPLACE');
            self::assertSame('REPLACED', $this->readLine($workerA, 'worker A replacement result'));

            $observerCommand = new PdoRuleCommandRepository($observer);
            $observerQuery = new PdoRuleManagementQuery($observer);
            $observerReader = new PdoActiveRuleReader($observer);
            $observerTransactionRunner = new PdoSavepointTransactionRunner($observer);
            $observerManagement = new EligibilityManagementService(
                $observerCommand,
                $observerQuery,
                $observerCommand,
                $observerTransactionRunner,
            );
            $observerEvaluation = new EligibilityEvaluationService($observerReader);
            $committedBefore = $observerManagement->inspectRules(new RuleCriteria(
                $subject,
                'country',
                lifecycle: RuleLifecycleEnum::ACTIVE,
            ));
            self::assertSame(
                [['OLD', RuleEffectEnum::ALLOW]],
                array_map(
                    static fn (\Maatify\Eligibility\Rule\Rule $rule): array => [
                        $rule->dimensionValue,
                        $rule->effect,
                    ],
                    $committedBefore->items(),
                ),
            );
            self::assertSame(DecisionReasonEnum::ELIGIBLE, $observerEvaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'OLD')),
            )->reasonCode);
            self::assertSame(DecisionReasonEnum::DENIED, $observerEvaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'A')),
            )->reasonCode);

            $this->signal($workerA, 'COMMIT');
            self::assertSame('DONE', $this->readLine($workerA, 'worker A commit result'));
            self::assertSame('DONE', $this->readLine($workerB, 'worker B replacement result'));

            $committedAfter = $observerManagement->inspectRules(new RuleCriteria(
                $subject,
                'country',
                lifecycle: RuleLifecycleEnum::ACTIVE,
            ));
            self::assertSame(
                [
                    ['B', RuleEffectEnum::ALLOW],
                    ['B2', RuleEffectEnum::DENY],
                ],
                array_map(
                    static fn (\Maatify\Eligibility\Rule\Rule $rule): array => [
                        $rule->dimensionValue,
                        $rule->effect,
                    ],
                    $committedAfter->items(),
                ),
            );
            self::assertSame(DecisionReasonEnum::ELIGIBLE, $observerEvaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'B')),
            )->reasonCode);
            self::assertSame(DecisionReasonEnum::DENIED, $observerEvaluation->decide(
                $subject,
                new Context(ContextDimension::fromStrings('country', 'A')),
            )->reasonCode);
        } catch (\Throwable $exception) {
            $primaryFailure = $exception;
            throw $exception;
        } finally {
            try {
                $this->closeWorkers($workerA, $workerB, $primaryFailure !== null);
            } finally {
                if ($observer->inTransaction()) {
                    $observer->rollBack();
                }
            }
        }

        $rules = $this->managementQuery->findByCriteria(new RuleCriteria($subject, lifecycle: \Maatify\Eligibility\Rule\RuleLifecycleEnum::ACTIVE));
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

        stream_set_blocking($stdin, false);
        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);
        stream_set_write_buffer($stdin, 0);

        return [
            'process' => $process,
            'stdin' => $stdin,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource} $worker
     */
    private function readLine(array $worker, string $event): string
    {
        $pipe = $worker['stdout'];
        $pipeId = get_resource_id($pipe);
        $buffer = $this->stdoutBuffers[$pipeId] ?? '';
        $deadline = ConcurrencyTimeout::deadline();

        while (true) {
            $lineEnd = strpos($buffer, PHP_EOL);
            if ($lineEnd !== false) {
                $line = substr($buffer, 0, $lineEnd);
                $this->stdoutBuffers[$pipeId] = substr($buffer, $lineEnd + strlen(PHP_EOL));

                return trim($line);
            }

            if (feof($pipe)) {
                $this->stdoutBuffers[$pipeId] = $buffer;
                $this->failWorkerWait($worker, sprintf(
                    'Concurrency worker closed its output while waiting for %s.',
                    $event,
                ));
            }

            if (ConcurrencyTimeout::expired($deadline)) {
                $this->failWorkerWait($worker, sprintf(
                    'Timed out waiting for %s after %d seconds.',
                    $event,
                    ConcurrencyTimeout::SECONDS,
                ));
            }

            [$seconds, $microseconds] = ConcurrencyTimeout::selectTimeout($deadline);
            $read = [$pipe];
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($ready === false) {
                $this->failWorkerWait($worker, sprintf(
                    'Could not wait for %s because worker output polling failed.',
                    $event,
                ));
            }
            if ($ready === 0) {
                $this->failWorkerWait($worker, sprintf(
                    'Timed out waiting for %s after %d seconds.',
                    $event,
                    ConcurrencyTimeout::SECONDS,
                ));
            }

            $chunk = fread($pipe, 8192);
            if ($chunk === false) {
                $this->failWorkerWait($worker, sprintf(
                    'Could not read %s from the concurrency worker.',
                    $event,
                ));
            }
            if ($chunk !== '') {
                $buffer .= $chunk;
            }
        }
    }

    /**
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource} $worker
     */
    private function signal(array $worker, string $signal): void
    {
        $pipe = $worker['stdin'];
        $payload = $signal . PHP_EOL;
        $offset = 0;
        $deadline = ConcurrencyTimeout::deadline();

        while ($offset < strlen($payload)) {
            $written = fwrite($pipe, substr($payload, $offset));
            if ($written === false) {
                $this->failWorkerWait($worker, sprintf(
                    'Could not send signal %s to the concurrency worker.',
                    $signal,
                ));
            }
            if ($written > 0) {
                $offset += $written;
                continue;
            }

            if (ConcurrencyTimeout::expired($deadline)) {
                $this->failWorkerWait($worker, sprintf(
                    'Timed out sending signal %s after %d seconds.',
                    $signal,
                    ConcurrencyTimeout::SECONDS,
                ));
            }

            [$seconds, $microseconds] = ConcurrencyTimeout::selectTimeout($deadline);
            $read = null;
            $write = [$pipe];
            $except = null;
            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($ready === false) {
                $this->failWorkerWait($worker, sprintf(
                    'Could not send signal %s because worker input polling failed.',
                    $signal,
                ));
            }
            if ($ready === 0) {
                $this->failWorkerWait($worker, sprintf(
                    'Timed out sending signal %s after %d seconds.',
                    $signal,
                    ConcurrencyTimeout::SECONDS,
                ));
            }
        }
    }

    /**
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource}|null $worker
     */
    private function closeWorker(?array $worker, bool $allowNonZeroExit = false): void
    {
        if ($worker === null) {
            return;
        }

        $pipeId = get_resource_id($worker['stdout']);
        fclose($worker['stdin']);
        $status = $this->waitForWorker($worker['process'], ConcurrencyTimeout::deadline());
        $terminated = false;

        if ($status['running']) {
            $terminated = true;
            proc_terminate($worker['process']);
            $status = $this->waitForWorker($worker['process'], ConcurrencyTimeout::deadline());
        }

        if ($status['running']) {
            proc_terminate($worker['process'], 9);
            $status = $this->waitForWorker($worker['process'], ConcurrencyTimeout::deadline());
        }

        try {
            if ($status['running']) {
                self::fail('Concurrency worker did not terminate within the cleanup deadline.');
            }

            fclose($worker['stdout']);
            fclose($worker['stderr']);
            $exitCode = proc_close($worker['process']);
            if (!$terminated && !$allowNonZeroExit) {
                self::assertSame(0, $exitCode);
            }
        } finally {
            if (is_resource($worker['stdout'])) {
                fclose($worker['stdout']);
            }
            if (is_resource($worker['stderr'])) {
                fclose($worker['stderr']);
            }
            unset($this->stdoutBuffers[$pipeId]);
        }
    }

    /**
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource}|null $workerA
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource}|null $workerB
     */
    private function closeWorkers(
        ?array $workerA,
        ?array $workerB,
        bool $preserveOriginalFailure = false,
        bool $allowNonZeroExit = false,
    ): void
    {
        $cleanupFailure = null;
        foreach ([$workerA, $workerB] as $worker) {
            try {
                $this->closeWorker($worker, $allowNonZeroExit);
            } catch (\Throwable $exception) {
                $cleanupFailure ??= $exception;
            }
        }

        if ($cleanupFailure !== null && !$preserveOriginalFailure) {
            throw $cleanupFailure;
        }
    }

    /**
     * @param resource $process
     * @return array{command: string, pid: int, running: bool, signaled: bool, stopped: bool, exitcode: int, termsig: int, stopsig: int}
     */
    private function waitForWorker($process, int $deadline): array
    {
        do {
            $status = proc_get_status($process);
            if (!$status['running'] || ConcurrencyTimeout::expired($deadline)) {
                return $status;
            }

            usleep(10_000);
        } while (true);
    }

    /**
     * @param array{process: resource, stdin: resource, stdout: resource, stderr: resource} $worker
     * @return never
     */
    private function failWorkerWait(array $worker, string $message): never
    {
        $diagnostic = $this->readAvailable($worker['stderr']);
        if ($diagnostic !== '') {
            $message .= ' stderr: ' . $diagnostic;
        }

        self::fail($message);
    }

    /** @param resource $pipe */
    private function readAvailable($pipe): string
    {
        $contents = '';
        while (true) {
            $chunk = fread($pipe, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $contents .= $chunk;
        }

        return trim(substr($contents, 0, 4096));
    }
}
