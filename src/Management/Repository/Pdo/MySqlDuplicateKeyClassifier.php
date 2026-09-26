<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Repository\Pdo;

/**
 * @internal MySQL/MariaDB driver-specific evidence used by the PDO repository.
 */
final class MySqlDuplicateKeyClassifier
{
    private function __construct() {}

    /** Identifies only the MySQL duplicate-key condition used for Rule identity translation. */
    public static function isDuplicate(\PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;

        return (is_int($driverCode) && $driverCode === 1062)
            || (is_string($driverCode) && $driverCode === '1062');
    }
}
