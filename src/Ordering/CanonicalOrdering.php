<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Ordering;

final class CanonicalOrdering
{
    private function __construct()
    {
    }

    public static function compareStrings(string $left, string $right): int
    {
        return strcmp($left, $right);
    }
}
