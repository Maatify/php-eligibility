<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Validation;

use InvalidArgumentException;

final class CanonicalString
{
    private function __construct()
    {
    }

    public static function validate(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('%s must be a string.', $field));
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('%s must contain valid UTF-8.', $field));
        }

        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $field));
        }

        if (preg_match('/\A(?:\s|\p{Z})*\z/u', $value) === 1) {
            throw new InvalidArgumentException(sprintf('%s must contain a non-whitespace character.', $field));
        }

        if (
            preg_match('/\A(?:\s|\p{Z})/u', $value) === 1
            || preg_match('/(?:\s|\p{Z})\z/u', $value) === 1
        ) {
            throw new InvalidArgumentException(sprintf('%s must not have leading or trailing whitespace.', $field));
        }

        return $value;
    }
}
