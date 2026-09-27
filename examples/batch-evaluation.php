<?php

declare(strict_types=1);

use Maatify\Eligibility\ValueObject\Subject;
use Maatify\Eligibility\ValueObject\SubjectCollection;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationServiceInterface;
use Maatify\Eligibility\Evaluation\ValueObject\Context;
use Maatify\Eligibility\Evaluation\ValueObject\ContextDimension;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Service\EligibilityManagementServiceInterface;
use Maatify\Eligibility\Factory\Pdo\PdoEligibilityRuntimeFactory;
use Maatify\Eligibility\Enum\RuleEffectEnum;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @return array{management: EligibilityManagementServiceInterface, evaluation: EligibilityEvaluationServiceInterface}
 */
function wireBatchServices(PDO $pdo): array
{
    $factory = new PdoEligibilityRuntimeFactory($pdo);

    return [
        'management' => $factory->createManagementService(),
        'evaluation' => $factory->createEvaluationService(),
    ];
}

function connectBatchDatabaseOrSkip(): PDO
{
    $required = [
        'ELIGIBILITY_DB_HOST',
        'ELIGIBILITY_DB_PORT',
        'ELIGIBILITY_DB_NAME',
        'ELIGIBILITY_DB_USER',
        'ELIGIBILITY_DB_PASSWORD',
    ];
    $missing = array_values(array_filter(
        $required,
        static fn(string $name): bool => getenv($name) === false || getenv($name) === '',
    ));

    if ($missing !== []) {
        fwrite(STDOUT, sprintf(
            "SKIP: set %s to run this example against a MySQL-compatible database.\n",
            implode(', ', $missing),
        ));
        exit(0);
    }

    $host = (string) getenv('ELIGIBILITY_DB_HOST');
    $databaseName = (string) getenv('ELIGIBILITY_DB_NAME');
    if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
        fwrite(STDOUT, "SKIP: ELIGIBILITY_DB_HOST must be 127.0.0.1 or localhost; refusing a non-local database.\n");
        exit(0);
    }
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_]*_test$/D', $databaseName) !== 1) {
        fwrite(STDOUT, "SKIP: ELIGIBILITY_DB_NAME must be a dedicated local test database ending in _test.\n");
        exit(0);
    }

    if (!extension_loaded('pdo_mysql')) {
        fwrite(STDOUT, "SKIP: ext-pdo_mysql is required for this persistence example.\n");
        exit(0);
    }

    $schema = file_get_contents(__DIR__ . '/../schema/eligibility_rules.sql');
    if ($schema === false) {
        throw new RuntimeException('The package schema asset could not be read.');
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            (string) getenv('ELIGIBILITY_DB_HOST'),
            (string) getenv('ELIGIBILITY_DB_PORT'),
            (string) getenv('ELIGIBILITY_DB_NAME'),
        ),
        (string) getenv('ELIGIBILITY_DB_USER'),
        (string) getenv('ELIGIBILITY_DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
    $pdo->exec($schema);

    return $pdo;
}

$pdo = connectBatchDatabaseOrSkip();
$services = wireBatchServices($pdo);
$management = $services['management'];
$evaluation = $services['evaluation'];

$first = new Subject('product', 'example-batch-first');
$second = new Subject('product', 'example-batch-second');
foreach ([$first, $second] as $subject) {
    $management->cleanupSubject(new CleanupSubjectCommand($subject));
}

$management->createRule(new CreateRuleCommand($first, 'country', 'EG', RuleEffectEnum::ALLOW));
$management->createRule(new CreateRuleCommand($second, 'country', 'KW', RuleEffectEnum::ALLOW));

$decisions = $evaluation->decideMany(
    new SubjectCollection($second, $first),
    new Context(ContextDimension::fromStrings('country', 'EG')),
);

echo "Input order is preserved in the returned SubjectDecisionCollectionDTO:\n";
echo json_encode($decisions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
