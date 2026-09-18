<?php

declare(strict_types=1);

use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DeactivateRuleCommand;
use Maatify\Eligibility\Management\Command\ReactivateRuleCommand;
use Maatify\Eligibility\Management\Command\UpdateRuleEffectCommand;
use Maatify\Eligibility\Management\Query\ActiveDimensionKeysQuery;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Rule\Repository\PdoRuleCommandRepository;
use Maatify\Eligibility\Rule\Repository\PdoRuleManagementQuery;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Persistence\Pdo\Transaction\PdoSavepointTransactionRunner;

require __DIR__ . '/../vendor/autoload.php';

function connectManagementDatabaseOrSkip(): PDO
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

$pdo = connectManagementDatabaseOrSkip();
$commandRepository = new PdoRuleCommandRepository($pdo);
$management = new EligibilityManagementService(
    $commandRepository,
    new PdoRuleManagementQuery($pdo),
    $commandRepository,
    new PdoSavepointTransactionRunner($pdo),
);

$subject = new Subject('product', 'example-management');
$management->cleanupSubject(new CleanupSubjectCommand($subject));

$created = $management->createRule(new CreateRuleCommand(
    $subject,
    'country',
    'EG',
    RuleEffectEnum::ALLOW,
));
$identity = $created->naturalIdentity();

$inspected = $management->inspectRule($identity);
$allRules = $management->inspectRules(new RuleCriteria($subject, 'country'));
$activeDimensions = $management->inspectActiveDimensionKeys(new ActiveDimensionKeysQuery($subject));

$management->updateRuleEffect(new UpdateRuleEffectCommand($identity, RuleEffectEnum::DENY));
$management->deactivateRule(new DeactivateRuleCommand($identity));
$inactiveRules = $management->inspectRules(new RuleCriteria(
    $subject,
    'country',
    RuleLifecycleEnum::INACTIVE,
));
$management->reactivateRule(new ReactivateRuleCommand($identity));
$reactivated = $management->inspectRule($identity);
$management->cleanupSubject(new CleanupSubjectCommand($subject));
$remainingRules = $management->inspectRules(new RuleCriteria($subject, 'country'));

echo json_encode([
    'created' => $created,
    'inspected' => $inspected,
    'allRules' => $allRules,
    'activeDimensionKeys' => $activeDimensions,
    'inactiveRules' => $inactiveRules,
    'reactivated' => $reactivated,
    'cleanup' => ['remainingRules' => $remainingRules->count()],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
