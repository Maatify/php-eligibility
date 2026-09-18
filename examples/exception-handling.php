<?php

declare(strict_types=1);

use Maatify\Eligibility\Common\Value\Subject;
use Maatify\Eligibility\Exception\EligibilityExceptionInterface;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Host-owned numeric identifiers must be converted to canonical strings
    // before they cross the package boundary.
    new Subject('product', 150);
} catch (EligibilityExceptionInterface $exception) {
    echo sprintf(
        "Caught typed Eligibility failure: %s (%s)\n",
        $exception::class,
        $exception->getMessage(),
    );
}

echo "Known package failures use EligibilityExceptionInterface; unknown PDOException/Throwable values are propagated unchanged.\n";
