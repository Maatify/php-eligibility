<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Throwable;

/**
 * Signals that a persisted Rule row could not be classified under Eligibility's
 * own invariants (structural shape, canonical component, effect, or lifecycle).
 *
 * This is a package-owned semantic classification of malformed persisted state;
 * it is distinct from an unknown/external storage failure, which propagates
 * unchanged instead of being converted to this exception.
 */
final class InvalidPersistedRuleStateException extends SystemMaatifyException implements EligibilityExceptionInterface
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    protected function defaultErrorCode(): ErrorCodeInterface
    {
        return ErrorCodeEnum::MAATIFY_ERROR;
    }
}
