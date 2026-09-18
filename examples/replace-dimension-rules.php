<?php

declare(strict_types=1);

use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository;
use Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;

require __DIR__ . '/../vendor/autoload.php';

function connectReplacementDatabaseOrSkip(): PDO
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

$pdo = connectReplacementDatabaseOrSkip();
$commandRepository = new PdoRuleCommandRepository($pdo);
$management = new EligibilityManagementService(
    $commandRepository,
    new PdoRuleManagementQuery($pdo),
    $commandRepository,
    new PdoSavepointTransactionRunner($pdo),
);

$subject = new Subject('product', 'example-replacement');
$management->cleanupSubject(new CleanupSubjectCommand($subject));

$pdo->beginTransaction();
try {
    $management->replaceDimensionRules(new ReplaceDimensionRulesCommand(
        $subject,
        'country',
        new DesiredRuleCollection(
            new DesiredRule('EG', RuleEffectEnum::ALLOW),
            new DesiredRule('KW', RuleEffectEnum::ALLOW),
        ),
    ));
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

$rules = $management->inspectRules(new RuleCriteria($subject, 'country'));
echo "Replacement committed inside the Host-owned outer transaction:\n";
echo json_encode($rules, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
