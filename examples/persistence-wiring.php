<?php

declare(strict_types=1);

use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Rule\Repository\PdoActiveRuleReader;
use Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository;
use Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;

require __DIR__ . '/../vendor/autoload.php';

function connectWiringDatabaseOrSkip(): PDO
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
        static fn (string $name): bool => getenv($name) === false || getenv($name) === '',
    ));

    if ($missing !== []) {
        fwrite(STDOUT, sprintf(
            "SKIP: set %s to run this example against a MySQL-compatible database.\n",
            implode(', ', $missing),
        ));
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
            getenv('ELIGIBILITY_DB_HOST'),
            getenv('ELIGIBILITY_DB_PORT'),
            getenv('ELIGIBILITY_DB_NAME'),
        ),
        getenv('ELIGIBILITY_DB_USER'),
        getenv('ELIGIBILITY_DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
    $pdo->exec($schema);

    return $pdo;
}

$pdo = connectWiringDatabaseOrSkip();

// Every adapter and the shared transaction runner receive this exact PDO instance.
$commandRepository = new PdoRuleCommandRepository($pdo);
$managementQuery = new PdoRuleManagementQuery($pdo);
$activeRuleReader = new PdoActiveRuleReader($pdo);
$transactionRunner = new PdoSavepointTransactionRunner($pdo);

$management = new EligibilityManagementService(
    $commandRepository,
    $managementQuery,
    $commandRepository,
    $transactionRunner,
);
$evaluation = new EligibilityEvaluationService($activeRuleReader);

echo sprintf(
    "PDO wiring ready: %s and %s share one PDO connection.\n",
    $management::class,
    $evaluation::class,
);
