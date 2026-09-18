<?php

declare(strict_types=1);

use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository;
use Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Tests\Support\ConcurrencyTimeout;
use Maatify\Eligibility\Tests\Support\IntegrationDatabase;
use Maatify\Eligibility\Common\Value\Subject;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/ConcurrencyTimeout.php';

/** @var mixed $rawArguments */
$rawArguments = $_SERVER['argv'] ?? [];
if (!is_array($rawArguments)) {
    throw new InvalidArgumentException('Worker arguments are unavailable.');
}

$arguments = [];
foreach (array_slice($rawArguments, 1) as $rawArgument) {
    if (!is_string($rawArgument)) {
        throw new InvalidArgumentException('Worker arguments must be strings.');
    }
    $arguments[] = $rawArgument;
}
$mode = $arguments[0] ?? '';

try {
    if ($mode === 'silent') {
        silentWorker();
        exit(0);
    }

    if ($mode === 'await-signal') {
        awaitSignal();
        writeLine('RECEIVED');
        exit(0);
    }

    $pdo = IntegrationDatabase::connect();
    $repository = new PdoRuleCommandRepository($pdo);

    match ($mode) {
        'hold-create' => holdCreate($pdo, $repository, $arguments),
        'create' => create($repository, $arguments),
        'hold-replace' => holdReplace($pdo, $repository, $arguments),
        'replace' => replace($pdo, $repository, $arguments),
        default => throw new InvalidArgumentException('Unknown concurrency worker mode.'),
    };
} catch (Throwable $exception) {
    writeLine('ERROR:' . $exception::class . ':' . $exception->getMessage());
    exit(1);
}

/** @param list<string> $arguments */
function holdCreate(PDO $pdo, PdoRuleCommandRepository $repository, array $arguments): void
{
    $pdo->beginTransaction();
    try {
        $repository->create(createCommand($arguments));
        writeLine('CREATED');
        awaitSignal();
        $pdo->commit();
        writeLine('DONE');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

/** @param list<string> $arguments */
function create(PdoRuleCommandRepository $repository, array $arguments): void
{
    writeLine('STARTED');
    try {
        $repository->create(createCommand($arguments));
        writeLine('RESULT:SUCCESS');
    } catch (RuleIdentityConflictException) {
        writeLine('RESULT:DUPLICATE');
    }
}

/** @param list<string> $arguments */
function holdReplace(PDO $pdo, PdoRuleCommandRepository $repository, array $arguments): void
{
    $subject = subjectFromArguments($arguments);
    $pdo->beginTransaction();
    try {
        $repository->lockSubjectForMutation($subject);
        writeLine('LOCKED');
        awaitSignal();
        (new EligibilityManagementService($repository, new PdoRuleManagementQuery($pdo)))->replaceDimensionRules(
            replacementCommand($arguments),
        );
        writeLine('REPLACED');
        awaitSignal();
        $pdo->commit();
        writeLine('DONE');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

/** @param list<string> $arguments */
function replace(PDO $pdo, PdoRuleCommandRepository $repository, array $arguments): void
{
    writeLine('STARTED');
    writeLine('ATTEMPTING_LOCK');
    (new EligibilityManagementService(
        $repository,
        new PdoRuleManagementQuery($pdo),
    ))->replaceDimensionRules(
        replacementCommand($arguments),
    );
    writeLine('DONE');
}

/** @param list<string> $arguments */
function createCommand(array $arguments): CreateRuleCommand
{
    if (count($arguments) !== 6) {
        throw new InvalidArgumentException('Create worker arguments are invalid.');
    }

    $effect = RuleEffectEnum::tryFrom($arguments[5]);
    if ($effect === null) {
        throw new InvalidArgumentException('Create worker effect is invalid.');
    }

    return new CreateRuleCommand(
        new Subject($arguments[1], $arguments[2]),
        $arguments[3],
        $arguments[4],
        $effect,
    );
}

/** @param list<string> $arguments */
function replacementCommand(array $arguments): ReplaceDimensionRulesCommand
{
    if (count($arguments) !== 5) {
        throw new InvalidArgumentException('Replacement worker arguments are invalid.');
    }

    return new ReplaceDimensionRulesCommand(
        subjectFromArguments($arguments),
        $arguments[3],
        desiredRules($arguments[4]),
    );
}

/** @param list<string> $arguments */
function subjectFromArguments(array $arguments): Subject
{
    if (count($arguments) < 3) {
        throw new InvalidArgumentException('Subject worker arguments are invalid.');
    }

    return new Subject($arguments[1], $arguments[2]);
}

function desiredRules(string $encoded): DesiredRuleCollection
{
    $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Desired rules must be a JSON array.');
    }

    $desired = [];
    foreach ($decoded as $item) {
        if (!is_array($item) || count($item) !== 2) {
            throw new InvalidArgumentException('A desired worker rule must contain value and effect.');
        }

        $value = $item[0] ?? null;
        $effectValue = $item[1] ?? null;
        if (!is_string($value) || !is_string($effectValue)) {
            throw new InvalidArgumentException('Desired worker rule fields must be strings.');
        }

        $effect = RuleEffectEnum::tryFrom($effectValue);
        if ($effect === null) {
            throw new InvalidArgumentException('Desired worker rule effect is invalid.');
        }

        $desired[] = new DesiredRule($value, $effect);
    }

    return new DesiredRuleCollection(...$desired);
}

function awaitSignal(): void
{
    stream_set_blocking(STDIN, false);
    $deadline = ConcurrencyTimeout::deadline();

    while (true) {
        if (ConcurrencyTimeout::expired($deadline)) {
            throw new RuntimeException(sprintf(
                'Concurrency worker timed out waiting for its signal after %d seconds.',
                ConcurrencyTimeout::SECONDS,
            ));
        }

        [$seconds, $microseconds] = ConcurrencyTimeout::selectTimeout($deadline);
        $read = [STDIN];
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
        if ($ready === false) {
            throw new RuntimeException('Concurrency worker signal polling failed.');
        }
        if ($ready === 0) {
            throw new RuntimeException(sprintf(
                'Concurrency worker timed out waiting for its signal after %d seconds.',
                ConcurrencyTimeout::SECONDS,
            ));
        }

        $signal = fgets(STDIN);
        if ($signal === false) {
            if (feof(STDIN)) {
                throw new RuntimeException('Concurrency worker input closed before its signal arrived.');
            }

            continue;
        }
        if (trim($signal) !== '') {
            return;
        }
    }
}

function writeLine(string $message): void
{
    stream_set_blocking(STDOUT, false);
    stream_set_write_buffer(STDOUT, 0);
    $payload = $message . PHP_EOL;
    $offset = 0;
    $deadline = ConcurrencyTimeout::deadline();

    while ($offset < strlen($payload)) {
        $written = fwrite(STDOUT, substr($payload, $offset));
        if ($written === false) {
            throw new RuntimeException('Concurrency worker output pipe could not be written.');
        }
        if ($written > 0) {
            $offset += $written;
            continue;
        }
        if (ConcurrencyTimeout::expired($deadline)) {
            throw new RuntimeException(sprintf(
                'Concurrency worker timed out writing output after %d seconds.',
                ConcurrencyTimeout::SECONDS,
            ));
        }

        [$seconds, $microseconds] = ConcurrencyTimeout::selectTimeout($deadline);
        $read = null;
        $write = [STDOUT];
        $except = null;
        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);
        if ($ready === false) {
            throw new RuntimeException('Concurrency worker output polling failed.');
        }
        if ($ready === 0) {
            throw new RuntimeException(sprintf(
                'Concurrency worker timed out writing output after %d seconds.',
                ConcurrencyTimeout::SECONDS,
            ));
        }
    }
}

function silentWorker(): void
{
    $deadline = ConcurrencyTimeout::silentWorkerDeadline();
    while (!ConcurrencyTimeout::expired($deadline)) {
        usleep(10_000);
    }
}
