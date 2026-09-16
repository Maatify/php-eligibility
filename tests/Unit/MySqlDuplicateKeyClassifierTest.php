<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Rule\Repository\MySqlDuplicateKeyClassifier;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MySqlDuplicateKeyClassifierTest extends TestCase
{
    #[Test]
    public function mysqlDuplicateDriverCodeIsDuplicateEvidence(): void
    {
        $exception = new PDOException('duplicate');
        $exception->errorInfo = ['23000', 1062, 'Duplicate entry'];

        self::assertTrue(MySqlDuplicateKeyClassifier::isDuplicate($exception));
    }

    #[Test]
    public function integritySqlStateWithoutDuplicateDriverCodeIsNotDuplicateEvidence(): void
    {
        $exception = new PDOException('foreign key');
        $exception->errorInfo = ['23000', 1451, 'Cannot delete'];

        self::assertFalse(MySqlDuplicateKeyClassifier::isDuplicate($exception));
    }

    #[Test]
    public function missingDriverErrorInfoIsNotDuplicateEvidence(): void
    {
        self::assertFalse(MySqlDuplicateKeyClassifier::isDuplicate(new PDOException('unknown')));
    }
}
