<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Management\Repository\Pdo\MySqlConcurrencyConflictClassifier;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MySqlConcurrencyConflictClassifierTest extends TestCase
{
    #[Test]
    public function mysqlLockWaitTimeoutDriverCodeIsConcurrencyEvidence(): void
    {
        $exception = new PDOException('lock wait timeout');
        $exception->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];

        self::assertTrue(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function mysqlDeadlockDriverCodeIsConcurrencyEvidence(): void
    {
        $exception = new PDOException('deadlock');
        $exception->errorInfo = ['40001', 1213, 'Deadlock found'];

        self::assertTrue(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function mysqlDuplicateKeyDriverCodeIsNotConcurrencyEvidence(): void
    {
        $exception = new PDOException('duplicate');
        $exception->errorInfo = ['23000', 1062, 'Duplicate entry'];

        self::assertFalse(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function unknownIntegerDriverCodeIsNotConcurrencyEvidence(): void
    {
        $exception = new PDOException('missing table');
        $exception->errorInfo = ['42S02', 1146, "Table doesn't exist"];

        self::assertFalse(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function stringLockWaitTimeoutDriverCodeIsConcurrencyEvidence(): void
    {
        $exception = new PDOException('lock wait timeout');
        $exception->errorInfo = ['HY000', '1205', 'Lock wait timeout exceeded'];

        self::assertTrue(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function stringDeadlockDriverCodeIsConcurrencyEvidence(): void
    {
        $exception = new PDOException('deadlock');
        $exception->errorInfo = ['40001', '1213', 'Deadlock found'];

        self::assertTrue(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function paddedNumericStringDriverCodeIsNotConcurrencyEvidence(): void
    {
        $exception = new PDOException('lock wait timeout with padded code');
        $exception->errorInfo = ['HY000', '01205', 'Lock wait timeout exceeded'];

        self::assertFalse(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }

    #[Test]
    public function missingDriverErrorInfoIsNotConcurrencyEvidence(): void
    {
        self::assertFalse(MySqlConcurrencyConflictClassifier::isConcurrencyConflict(new PDOException('unknown')));
    }

    #[Test]
    public function nullDriverErrorCodeIsNotConcurrencyEvidence(): void
    {
        $exception = new PDOException('malformed');
        $exception->errorInfo = ['HY000', null, 'General error'];

        self::assertFalse(MySqlConcurrencyConflictClassifier::isConcurrencyConflict($exception));
    }
}
