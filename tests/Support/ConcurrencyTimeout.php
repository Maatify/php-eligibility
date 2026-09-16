<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Support;

final class ConcurrencyTimeout
{
    public const SECONDS = 2;

    private const NANOSECONDS_PER_SECOND = 1_000_000_000;

    private function __construct()
    {
    }

    public static function deadline(): int
    {
        return hrtime(true) + (self::SECONDS * self::NANOSECONDS_PER_SECOND);
    }

    /** @return array{0: int, 1: int} */
    public static function selectTimeout(int $deadline): array
    {
        $remaining = $deadline - hrtime(true);
        if ($remaining < 0) {
            $remaining = 0;
        }

        return [
            intdiv($remaining, self::NANOSECONDS_PER_SECOND),
            intdiv($remaining % self::NANOSECONDS_PER_SECOND, 1_000),
        ];
    }

    public static function expired(int $deadline): bool
    {
        return hrtime(true) >= $deadline;
    }
}
