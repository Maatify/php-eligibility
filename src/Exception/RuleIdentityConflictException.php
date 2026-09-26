<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Eligibility\ValueObject\RuleIdentity;
use Maatify\Exceptions\Exception\Conflict\GenericConflictMaatifyException;

/**
 * Signals that persistence already contains the requested natural Rule identity.
 */
final class RuleIdentityConflictException extends GenericConflictMaatifyException implements EligibilityExceptionInterface
{
    private RuleIdentity $identity;

    public function __construct(RuleIdentity $identity, ?\Throwable $previous = null)
    {
        $this->identity = $identity;

        parent::__construct(
            sprintf(
                'An Eligibility Rule already exists for %s:%s / %s:%s.',
                $identity->subjectType,
                $identity->subjectId,
                $identity->dimensionKey,
                $identity->dimensionValue,
            ),
            0,
            $previous,
        );
    }

    /** Returns the conflicting identity without exposing a raw database exception as the contract. */
    public function identity(): RuleIdentity
    {
        return $this->identity;
    }
}
