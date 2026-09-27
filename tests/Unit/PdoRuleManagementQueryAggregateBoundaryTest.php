<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Exception\InvalidPersistedRuleStateException;
use Maatify\Eligibility\Management\Repository\Pdo\PdoRuleManagementQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Focused reflection-level evidence for the private aggregate-hydration
 * boundary used by `PdoRuleManagementQuery::summarizeLifecycle()`. A real
 * `COUNT(*)`/`SUM(...)` cannot practically produce a value above
 * `PHP_INT_MAX` without an unreasonable dataset, so this exercises the exact
 * same private method directly instead.
 */
final class PdoRuleManagementQueryAggregateBoundaryTest extends TestCase
{
    #[Test]
    public function acceptsExplicitNullAsAnEmptySumWhenAllowed(): void
    {
        self::assertSame(0, $this->readColumn(['count' => null], true));
    }

    #[Test]
    public function rejectsMissingAggregateColumn(): void
    {
        $this->expectException(InvalidPersistedRuleStateException::class);

        $this->readColumn([]);
    }

    #[Test]
    public function rejectsExplicitNullWhenTheAggregateDisallowsIt(): void
    {
        $this->expectException(InvalidPersistedRuleStateException::class);

        $this->readColumn(['count' => null]);
    }

    #[Test]
    public function acceptsNativeNonNegativeIntegers(): void
    {
        self::assertSame(0, $this->readColumn(['count' => 0]));
        self::assertSame(5, $this->readColumn(['count' => 5]));
    }

    #[Test]
    public function acceptsPhpIntMaxAsAnExactDigitString(): void
    {
        self::assertSame(PHP_INT_MAX, $this->readColumn(['count' => (string) PHP_INT_MAX]));
    }

    #[Test]
    public function acceptsLeadingZeroesAsTheirExactIntendedMagnitude(): void
    {
        self::assertSame(7, $this->readColumn(['count' => '0007']));
        self::assertSame(0, $this->readColumn(['count' => '0000']));
    }

    #[Test]
    public function rejectsOneAbovePhpIntMax(): void
    {
        $this->expectException(InvalidPersistedRuleStateException::class);

        $this->readColumn(['count' => $this->onePastPhpIntMax()]);
    }

    #[Test]
    public function rejectsNativeNegativeIntegers(): void
    {
        $this->expectException(InvalidPersistedRuleStateException::class);

        $this->readColumn(['count' => -1]);
    }

    #[Test]
    public function rejectsNegativeDecimalScientificAndSignedStringRepresentations(): void
    {
        foreach (['-1', '1.5', '1e2', '1E2', '+1', 'abc', ''] as $value) {
            try {
                $this->readColumn(['count' => $value]);
                self::fail(sprintf('Expected InvalidPersistedRuleStateException for aggregate value "%s".', $value));
            } catch (InvalidPersistedRuleStateException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function readColumn(array $row, bool $allowNull = false): int
    {
        $query = (new ReflectionClass(PdoRuleManagementQuery::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(PdoRuleManagementQuery::class, 'exactNonNegativeIntegerColumn');

        /** @var int $result */
        $result = $method->invoke($query, $row, 'count', $allowNull);

        return $result;
    }

    /** `PHP_INT_MAX + 1`, computed as an exact digit string without float arithmetic. */
    private function onePastPhpIntMax(): string
    {
        $digits = (string) PHP_INT_MAX;
        $lastDigit = (int) $digits[-1];

        return substr($digits, 0, -1) . (string) ($lastDigit + 1);
    }
}
