<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Maatify\Eligibility\Management\Command\CleanupSubjectCommand;
use Maatify\Eligibility\Management\Command\CreateRuleCommand;
use Maatify\Eligibility\Management\Command\DesiredRule;
use Maatify\Eligibility\Management\Command\DesiredRuleCollection;
use Maatify\Eligibility\Management\Command\ReplaceDimensionRulesCommand;
use Maatify\Eligibility\Management\Query\RuleCriteria;
use Maatify\Eligibility\Evaluation\Service\EligibilityEvaluationService;
use Maatify\Eligibility\Management\Service\EligibilityManagementService;
use Maatify\Eligibility\Evaluation\Decision\DecisionReasonEnum;
use Maatify\Eligibility\Rule\Repository\PdoRuleRepository;
use Maatify\Eligibility\Rule\RuleEffectEnum;
use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Eligibility\Rule\RuleLifecycleEnum;
use Maatify\Eligibility\Common\Value\Context;
use Maatify\Eligibility\Common\Value\ContextDimension;
use Maatify\Eligibility\Common\Value\Subject;

$consumerRoot = dirname(__DIR__);
$autoloadPath = $consumerRoot . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    fail('Consumer production autoload is missing.');
}

require $autoloadPath;

$packageName = 'maatify/php-eligibility';
$packagePath = InstalledVersions::getInstallPath($packageName);
if (!is_string($packagePath) || $packagePath === '' || !is_dir($packagePath)) {
    fail('The Eligibility package was not installed as a Composer dependency.');
}
if (is_link($packagePath)) {
    fail('The Eligibility package was installed as a symlink; copied path installation is required.');
}

$packageRealPath = realpath($packagePath);
$sourceRealPath = realpath($packagePath . '/src');
if ($packageRealPath === false || $sourceRealPath === false) {
    fail('The installed Eligibility package path is not readable.');
}

/** @var array<string, list<string>> $productionPsr4 */
$productionPsr4 = require $consumerRoot . '/vendor/composer/autoload_psr4.php';
$autoloadedSource = $productionPsr4['Maatify\\Eligibility\\'][0] ?? null;
if (!is_string($autoloadedSource) || realpath($autoloadedSource) !== $sourceRealPath) {
    fail('Production PSR-4 autoload does not point to the installed package src/.');
}
if (isset($productionPsr4['Maatify\\Eligibility\\Tests\\'])) {
    fail('The package autoload-dev namespace is present in the consumer autoload.');
}

$schemaPath = $packageRealPath . '/schema/eligibility_rules.sql';
$schema = file_get_contents($schemaPath);
if ($schema === false) {
    fail('The documented package schema asset is missing from the installed dependency.');
}

$pdo = connectToRealMysql();
$pdo->exec($schema);

$runId = environment('ELIGIBILITY_HARNESS_RUN_ID', 'manual');
$subject = new Subject('consumer_harness', $runId);
$repository = new PdoRuleRepository($pdo);
$management = new EligibilityManagementService($repository);
$evaluation = new EligibilityEvaluationService($repository);

assertTableEmpty($pdo, 'maa_eligibility_rules', 'Before workflow');
assertTableEmpty($pdo, 'maa_eligibility_subject_locks', 'Before workflow');
if ($management->inspectRules(new RuleCriteria($subject))->count() !== 0) {
    fail('Consumer state was not clean before the public workflow started.');
}
echo "REAL_MYSQL_PRE_RESIDUE=PASS\n";

$management->createRule(new CreateRuleCommand(
    $subject,
    'country',
    'EG',
    RuleEffectEnum::ALLOW,
));
$management->createRule(new CreateRuleCommand(
    $subject,
    'customer_type',
    'retail',
    RuleEffectEnum::ALLOW,
));

$eligible = $evaluation->decide($subject, new Context(
    ContextDimension::fromStrings('country', 'EG'),
    ContextDimension::fromStrings('customer_type', 'retail'),
));
assertSameValue(DecisionReasonEnum::ELIGIBLE, $eligible->reasonCode, 'Initial public evaluation');

$denied = $evaluation->decide($subject, new Context(
    ContextDimension::fromStrings('country', 'KW'),
    ContextDimension::fromStrings('customer_type', 'retail'),
));
assertSameValue(DecisionReasonEnum::DENIED, $denied->reasonCode, 'Denied public evaluation');

$management->replaceDimensionRules(new ReplaceDimensionRulesCommand(
    $subject,
    'country',
    new DesiredRuleCollection(new DesiredRule('KW', RuleEffectEnum::ALLOW)),
));

$rules = $management->inspectRules(new RuleCriteria($subject, 'country'));
assertSameValue(2, $rules->count(), 'Public management lifecycle read count');
assertSameValue(
    RuleLifecycleEnum::INACTIVE,
    $management->inspectRule(new RuleIdentity(
        $subject->subjectType,
        $subject->subjectId,
        'country',
        'EG',
    ))->lifecycle,
    'Replaced EG lifecycle',
);
assertSameValue(
    RuleLifecycleEnum::ACTIVE,
    $management->inspectRule(new RuleIdentity(
        $subject->subjectType,
        $subject->subjectId,
        'country',
        'KW',
    ))->lifecycle,
    'Replaced KW lifecycle',
);

$replaced = $evaluation->decide($subject, new Context(
    ContextDimension::fromStrings('country', 'KW'),
    ContextDimension::fromStrings('customer_type', 'retail'),
));
assertSameValue(DecisionReasonEnum::ELIGIBLE, $replaced->reasonCode, 'Replaced public evaluation');

$management->cleanupSubject(new CleanupSubjectCommand($subject));
if ($management->inspectRules(new RuleCriteria($subject))->count() !== 0) {
    fail('Public cleanup did not remove all Rules for the consumer Subject.');
}
assertTableEmpty($pdo, 'maa_eligibility_rules', 'After workflow');
assertTableEmpty($pdo, 'maa_eligibility_subject_locks', 'After workflow');

echo "PRODUCTION_AUTOLOAD=PASS\n";
echo "PUBLIC_WORKFLOW=PASS\n";
echo "REAL_MYSQL_POST_RESIDUE=PASS\n";
echo "REAL_MYSQL_RESIDUE=PASS\n";
echo "CONSUMER_HARNESS_RUN=" . $runId . " RESULT=PASS\n";

function connectToRealMysql(): PDO
{
    $host = environment('ELIGIBILITY_HARNESS_DB_HOST', '127.0.0.1');
    $portValue = environment('ELIGIBILITY_HARNESS_DB_PORT', '13306');
    if (filter_var($portValue, FILTER_VALIDATE_INT) === false) {
        fail('ELIGIBILITY_HARNESS_DB_PORT must be an integer.');
    }

    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $portValue, environment('ELIGIBILITY_HARNESS_DB_NAME', 'maatify_eligibility_test')),
        environment('ELIGIBILITY_HARNESS_DB_USER', 'eligibility_test'),
        environment('ELIGIBILITY_HARNESS_DB_PASSWORD', 'eligibility_test'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
}

function environment(string $name, string $default): string
{
    $value = getenv($name);

    return is_string($value) && $value !== '' ? $value : $default;
}

/** @phpstan-impure */
function assertTableEmpty(PDO $pdo, string $table, string $phase): void
{
    if (!in_array($table, ['maa_eligibility_rules', 'maa_eligibility_subject_locks'], true)) {
        fail('Unexpected consumer residue table.');
    }

    $statement = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
    if ($statement === false) {
        fail(sprintf('%s residue check could not read %s.', $phase, $table));
    }

    $count = (int) $statement->fetchColumn();
    if ($count !== 0) {
        fail(sprintf('%s %s was not empty: %d rows remained.', $phase, $table, $count));
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fail(sprintf('%s did not match the expected public result.', $label));
    }
}

function fail(string $message): never
{
    fwrite(STDERR, 'CONSUMER_HARNESS_ERROR=' . $message . PHP_EOL);
    exit(1);
}
