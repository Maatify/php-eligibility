<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Eligibility\Rule\RuleIdentity;
use Maatify\Exceptions\Exception\NotFound\ResourceNotFoundMaatifyException;

final class RuleNotFoundException extends ResourceNotFoundMaatifyException implements EligibilityExceptionInterface
{
    private RuleIdentity $identity;

    public function __construct(RuleIdentity $identity, ?\Throwable $previous = null)
    {
        $this->identity = $identity;

        parent::__construct(
            sprintf(
                'Eligibility Rule not found for %s:%s / %s:%s.',
                $identity->subjectType,
                $identity->subjectId,
                $identity->dimensionKey,
                $identity->dimensionValue,
            ),
            0,
            $previous,
        );
    }

    public function identity(): RuleIdentity
    {
        return $this->identity;
    }
}
