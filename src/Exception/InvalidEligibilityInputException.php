<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

final class InvalidEligibilityInputException extends InvalidArgumentMaatifyException implements EligibilityExceptionInterface
{
}
