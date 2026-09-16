<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule\Repository;

/**
 * @internal MySQL/MariaDB driver-specific evidence used by the PDO repository.
 */
final class MySqlDuplicateKeyClassifier
{
    private function __construct()
    {
    }

    public static function isDuplicate(\PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;

        return (is_int($driverCode) && $driverCode === 1062)
            || (is_string($driverCode) && $driverCode === '1062');
    }
}
