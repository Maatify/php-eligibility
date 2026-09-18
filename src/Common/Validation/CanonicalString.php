<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Common\Validation;

use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

final class CanonicalString
{
    public const SUBJECT_TYPE_MAX_BYTES = 64;

    public const SUBJECT_ID_MAX_BYTES = 191;

    public const DIMENSION_KEY_MAX_BYTES = 64;

    public const DIMENSION_VALUE_MAX_BYTES = 255;

    private function __construct()
    {
    }

    public static function validate(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidEligibilityInputException(sprintf('%s must be a string.', $field));
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidEligibilityInputException(sprintf('%s must contain valid UTF-8.', $field));
        }

        if ($value === '') {
            throw new InvalidEligibilityInputException(sprintf('%s must not be empty.', $field));
        }

        if (preg_match('/\A(?:\s|\p{Z})*\z/u', $value) === 1) {
            throw new InvalidEligibilityInputException(sprintf('%s must contain a non-whitespace character.', $field));
        }

        if (
            preg_match('/\A(?:\s|\p{Z})/u', $value) === 1
            || preg_match('/(?:\s|\p{Z})\z/u', $value) === 1
        ) {
            throw new InvalidEligibilityInputException(sprintf('%s must not have leading or trailing whitespace.', $field));
        }

        return $value;
    }

    public static function validateSubjectType(mixed $value, string $field = 'subjectType'): string
    {
        return self::validateBounded($value, $field, self::SUBJECT_TYPE_MAX_BYTES);
    }

    public static function validateSubjectId(mixed $value, string $field = 'subjectId'): string
    {
        return self::validateBounded($value, $field, self::SUBJECT_ID_MAX_BYTES);
    }

    public static function validateDimensionKey(mixed $value, string $field = 'dimensionKey'): string
    {
        return self::validateBounded($value, $field, self::DIMENSION_KEY_MAX_BYTES);
    }

    public static function validateDimensionValue(mixed $value, string $field = 'dimensionValue'): string
    {
        return self::validateBounded($value, $field, self::DIMENSION_VALUE_MAX_BYTES);
    }

    private static function validateBounded(mixed $value, string $field, int $maxBytes): string
    {
        $canonical = self::validate($value, $field);
        if (strlen($canonical) > $maxBytes) {
            throw new InvalidEligibilityInputException(
                sprintf('%s must not exceed %d bytes.', $field, $maxBytes),
            );
        }

        return $canonical;
    }
}
