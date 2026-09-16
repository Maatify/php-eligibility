<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;

final class RuleConcurrencyConflictException extends GenericConflictMaatifyException implements EligibilityExceptionInterface
{
    public function __construct(string $message = 'Eligibility Rule concurrency could not be resolved safely.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
