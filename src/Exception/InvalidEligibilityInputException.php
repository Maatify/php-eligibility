<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Exception;

use Maatify\Exceptions\Exception\Validation\InvalidArgumentMaatifyException;

/** Signals rejected non-canonical or otherwise invalid public package input. */
final class InvalidEligibilityInputException extends InvalidArgumentMaatifyException implements EligibilityExceptionInterface {}
