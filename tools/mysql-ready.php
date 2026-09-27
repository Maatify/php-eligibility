<?php

declare(strict_types=1);

/**
 * MySQL fixture readiness check for maatify/php-eligibility.
 *
 * This is the single maintained readiness implementation invoked by the
 * repository-owned tools/run-integration.sh lifecycle. That orchestrator
 * discovers the endpoint, owns the ELIGIBILITY_TEST_DB_* exports, and starts
 * the disposable fixture declared by docker-compose.integration.yml. This
 * tool retries a real PDO connection for a bounded duration and uses the exit
 * code as the authoritative result: 0 when the service accepts real
 * connections, 1 otherwise.
 *
 * Credentials come from verification-scoped ELIGIBILITY_TEST_DB_* values
 * exported by the canonical orchestrator. No workflow-native service
 * container or repository secret is involved.
 */

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        fwrite(STDERR, sprintf('Missing required verification environment variable: %s%s', $name, PHP_EOL));
        exit(2);
    }

    return $value;
}

$host = requiredEnvironment('ELIGIBILITY_TEST_DB_HOST');
$port = requiredEnvironment('ELIGIBILITY_TEST_DB_PORT');
$name = requiredEnvironment('ELIGIBILITY_TEST_DB_NAME');
$user = requiredEnvironment('ELIGIBILITY_TEST_DB_USER');
$password = requiredEnvironment('ELIGIBILITY_TEST_DB_PASSWORD');

$deadline = microtime(true) + 90.0;

while (true) {
    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ],
        );
        exit(0);
    } catch (Throwable $exception) {
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, 'MySQL fixture did not become ready within the readiness budget.' . PHP_EOL);
            exit(1);
        }

        sleep(2);
    }
}
