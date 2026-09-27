<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;

/** Signals that a concurrent Rule mutation could not be resolved safely. */
final class RuleConcurrencyConflictException extends GenericConflictMaatifyException implements EligibilityExceptionInterface
{
    /**
     * Creates the package-classified unresolved Rule concurrency failure.
     *
     * The default message may be replaced by the caller, and any supplied
     * underlying cause is preserved as the exception's previous cause.
     */
    public function __construct(string $message = 'Eligibility Rule concurrency could not be resolved safely.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
