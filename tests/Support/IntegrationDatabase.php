<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Support;

use PDO;
use RuntimeException;

final class IntegrationDatabase
{
    private function __construct()
    {
    }

    public static function connect(): PDO
    {
        $host = self::environment('ELIGIBILITY_TEST_DB_HOST', '127.0.0.1');
        $port = self::port();
        $database = self::environment('ELIGIBILITY_TEST_DB_NAME', 'maatify_eligibility_test');
        $username = self::environment('ELIGIBILITY_TEST_DB_USER', 'eligibility_test');
        $password = self::environment('ELIGIBILITY_TEST_DB_PASSWORD', 'eligibility_test');

        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    public static function applySchema(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../../schema/eligibility_rules.sql');
        if ($schema === false) {
            throw new RuntimeException('Eligibility schema asset could not be read.');
        }

        $pdo->exec($schema);
    }

    public static function clearRules(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM `maa_eligibility_rules`');
        $pdo->exec('DELETE FROM `maa_eligibility_subject_locks`');
    }

    private static function environment(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function port(): int
    {
        $value = self::environment('ELIGIBILITY_TEST_DB_PORT', '13306');
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('ELIGIBILITY_TEST_DB_PORT must be an integer.');
        }

        return (int) $value;
    }
}
