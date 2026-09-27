<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository\Pdo;

/**
 * @internal MySQL/MariaDB driver-specific evidence used by the PDO repository.
 */
final class MySqlConcurrencyConflictClassifier
{
    private const LOCK_WAIT_TIMEOUT = 1205;

    private const DEADLOCK = 1213;

    private function __construct() {}

    /** Identifies only the MySQL lock-wait-timeout/deadlock driver codes used for concurrency translation. */
    public static function isConcurrencyConflict(\PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;

        return (is_int($driverCode) && ($driverCode === self::LOCK_WAIT_TIMEOUT || $driverCode === self::DEADLOCK))
            || (is_string($driverCode) && ($driverCode === (string) self::LOCK_WAIT_TIMEOUT || $driverCode === (string) self::DEADLOCK));
    }
}
