<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Common;

use Maatify\Eligibility\Common\CanonicalString;

/**
 * Provides deterministic bytewise ordering for already-canonical package strings.
 *
 * Inputs are validated rather than coerced, so ordering cannot introduce a
 * different identity from the one accepted by the package boundary.
 *
 * @internal Package implementation primitive for deterministic collection ordering;
 * consumers should use the typed collections and results that own observable order.
 */
final class CanonicalOrdering
{
    private function __construct() {}

    /** Compares two validated values by their exact UTF-8 byte sequence. */
    public static function compareStrings(mixed $left, mixed $right): int
    {
        return strcmp(
            CanonicalString::validate($left, 'left'),
            CanonicalString::validate($right, 'right'),
        );
    }
}
