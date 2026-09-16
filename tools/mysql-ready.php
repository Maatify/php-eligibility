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

$host = getenv('ELIGIBILITY_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('ELIGIBILITY_TEST_DB_PORT') ?: '13306';
$name = getenv('ELIGIBILITY_TEST_DB_NAME') ?: 'maatify_eligibility_test';
$user = getenv('ELIGIBILITY_TEST_DB_USER') ?: 'eligibility_test';
$password = getenv('ELIGIBILITY_TEST_DB_PASSWORD') ?: 'eligibility_test';

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