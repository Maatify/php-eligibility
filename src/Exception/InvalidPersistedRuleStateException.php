<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Exceptions\Contracts\ErrorCodeInterface;
use Maatify\Exceptions\Enum\ErrorCodeEnum;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Throwable;

/**
 * Signals package-owned malformed persisted Eligibility Rule state under the
 * package's own invariants, including Rule hydration corruption and
 * lifecycle-summary aggregate inconsistency.
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
