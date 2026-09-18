<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Common\Ordering;

use Maatify\Eligibility\Common\Validation\CanonicalString;

final class CanonicalOrdering
{
    private function __construct()
    {
    }

    public static function compareStrings(mixed $left, mixed $right): int
    {
        return strcmp(
            CanonicalString::validate($left, 'left'),
            CanonicalString::validate($right, 'right'),
        );
    }
}
