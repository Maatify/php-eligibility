<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Support;

use Maatify\Persistence\Pdo\Transaction\SavepointTransactionRunnerInterface;

/**
 * Minimal unit-test seam for proving service delegation without reimplementing
 * transaction ownership or savepoint behavior.
 */
final class SynchronousTransactionRunner implements SavepointTransactionRunnerInterface
{
    public int $runCount = 0;

    public function run(callable $callback): mixed
    {
        $this->runCount++;

        return $callback();
    }
}
