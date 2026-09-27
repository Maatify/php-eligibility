<?php

declare(strict_types=1);

$root = dirname(__DIR__);
requiredEnvironment('ELIGIBILITY_DB_HOST');
requiredEnvironment('ELIGIBILITY_DB_PORT');
requiredEnvironment('ELIGIBILITY_DB_NAME');
requiredEnvironment('ELIGIBILITY_DB_USER');
requiredEnvironment('ELIGIBILITY_DB_PASSWORD');
$examples = glob($root . '/examples/*.php');
if ($examples === false) {
    fail('Could not discover examples.');
}
sort($examples, SORT_STRING);
$bootstrap = connect();
$schema = file_get_contents($root . '/schema/eligibility_rules.sql');
if ($schema === false) {
    fail('Could not read the package schema asset.');
}
$bootstrap->exec($schema);
clearState($bootstrap);
$passed = 0;
$skipped = 0;
foreach ($examples as $example) {
    $pdo = connect();
    clearState($pdo);
    $command = [PHP_BINARY, $example];
    $result = run($command, $root);
    clearState($pdo);
    if ($result['code'] !== 0) {
        fail(basename($example) . " exited {$result['code']}\n" . $result['output']);
    }
    if (preg_match('/^SKIP:/m', $result['output']) === 1) {
        $skipped++;
        fail(basename($example) . ' unexpectedly emitted SKIP in canonical smoke mode.');
    }
    $passed++;
    echo 'EXAMPLE_PASS=' . basename($example) . PHP_EOL;
}
clearState(connect());
echo sprintf("EXAMPLES_RESULT=PASS DISCOVERED=%d PASSED=%d UNEXPECTED_SKIP=%d RESIDUE=PASS\n", count($examples), $passed, $skipped);

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        fail('Missing required example environment variable: ' . $name);
    }
    return $value;
}

function connect(): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', requiredEnvironment('ELIGIBILITY_DB_HOST'), requiredEnvironment('ELIGIBILITY_DB_PORT'), requiredEnvironment('ELIGIBILITY_DB_NAME')),
        requiredEnvironment('ELIGIBILITY_DB_USER'),
        requiredEnvironment('ELIGIBILITY_DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
    );
}
function clearState(PDO $pdo): void
{
    $pdo->exec('DELETE FROM `maa_eligibility_rules`');
    $pdo->exec('DELETE FROM `maa_eligibility_subject_locks`');
}
/** @param list<string> $command
 *  @return array{code: int, output: string}
 */
function run(array $command, string $cwd): array
{
    /** @var array<int, resource> $pipes */
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, getenv());
    if (!is_resource($process)) {
        fail('Could not start ' . json_encode($command, JSON_THROW_ON_ERROR));
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $output = ($stdout === false ? '' : $stdout) . ($stderr === false ? '' : $stderr);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'output' => $output];
}
function fail(string $message): never
{
    fwrite(STDERR, 'EXAMPLES_ERROR=' . $message . PHP_EOL);
    exit(1);
}
