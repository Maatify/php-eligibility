<?php

declare(strict_types=1);

/**
 * MySQL fixture readiness check for maatify/php-eligibility.
 *
 * This is the single maintained readiness implementation shared by the
 * ci-integration workflow and the local parity command
 * (tools/check-local.sh). It retries a real PDO connection against the
 * test-only MySQL fixture for a bounded duration and uses the exit code as
 * the authoritative result: 0 when the service accepts real connections,
 * 1 otherwise.
 *
 * Credentials come from the ELIGIBILITY_TEST_DB_* environment variables and
 * match docker-compose.integration.yml as well as the ci-integration service
 * container defaults. No repository secret is ever involved.
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
