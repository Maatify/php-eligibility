<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Common;

use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

/**
 * Validates the exact UTF-8 string representation used at package boundaries.
 *
 * Validation deliberately does not trim, normalize, change case, transliterate,
 * or coerce scalar values. The bounded helpers additionally enforce the
 * package's byte limits before a value enters a value object or persistence flow.
 */
final class CanonicalString
{
    public const SUBJECT_TYPE_MAX_BYTES = 64;

    public const SUBJECT_ID_MAX_BYTES = 191;

    public const DIMENSION_KEY_MAX_BYTES = 64;

    public const DIMENSION_VALUE_MAX_BYTES = 255;

    private function __construct() {}

    /**
     * Accepts only a non-empty valid UTF-8 string without edge whitespace or
     * scalar coercion; bounded callers apply their field-specific byte limit.
     *
     * @throws InvalidEligibilityInputException when the canonical boundary is violated.
     */
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
