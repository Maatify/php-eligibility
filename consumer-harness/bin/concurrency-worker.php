<?php

declare(strict_types=1);

use Maatify\Eligibility\Exception\RuleIdentityConflictException;
use Maatify\Eligibility\Factory\Pdo\PdoEligibilityRuntimeFactory;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Enum\RuleEffectEnum;
use Maatify\Eligibility\ValueObject\Subject;

require dirname(__DIR__) . '/vendor/autoload.php';
$role = $argv[1] ?? '';
if (!in_array($role, ['a', 'b'], true)) {
    fail('Worker role must be a or b.');
}
$pdo = connect();
$subject = new Subject('consumer_harness', required('ELIGIBILITY_HARNESS_CONCURRENCY_SUBJECT'));
$management = (new PdoEligibilityRuntimeFactory($pdo))->createManagementService();
$command = new CreateRuleCommand($subject, 'concurrency_dimension', 'same-value', RuleEffectEnum::ALLOW);
if ($role === 'a') {
    $pdo->beginTransaction();
    $management->createRule($command);
    echo "CREATED\n";
    fflush(STDOUT);
    stream_set_blocking(STDIN, false);
    $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $signal = fgets(STDIN);
        if ($signal !== false && trim($signal) === 'COMMIT') {
            $pdo->commit();
            echo "DONE\n";
            fflush(STDOUT);
            exit(0);
        }
        $read = [STDIN];
        $write = null;
        $except = null;
        stream_select($read, $write, $except, 0, 100000);
    }
    throw new RuntimeException('Worker A commit signal timed out.');
}
echo "STARTED\n";
fflush(STDOUT);
try {
    $management->createRule($command);
} catch (RuleIdentityConflictException) {
    echo "RESULT:DUPLICATE\n";
    fflush(STDOUT);
    exit(0);
}
fail('Worker B unexpectedly created a duplicate Rule.');

function connect(): PDO
{
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', required('ELIGIBILITY_HARNESS_DB_HOST'), required('ELIGIBILITY_HARNESS_DB_PORT'), required('ELIGIBILITY_HARNESS_DB_NAME')), required('ELIGIBILITY_HARNESS_DB_USER'), required('ELIGIBILITY_HARNESS_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
}
function required(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        fail('Missing environment variable: ' . $name);
    }
    return $value;
}
function fail(string $message): never
{
    fwrite(STDERR, 'WORKER_ERROR=' . $message . PHP_EOL);
    exit(1);
}
