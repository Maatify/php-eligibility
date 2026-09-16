<?php

namespace Maatify\Eligibility\Tests\Support;

use Maatify\Eligibility\Ordering\CanonicalOrdering;
use Maatify\Eligibility\Value\ContextValueCollection;

final class NonStrictConsumer
{
    public static function contains(ContextValueCollection $values, mixed $value): bool
    {
        return $values->contains($value);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return CanonicalOrdering::compareStrings($left, $right);
    }
}
