<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Tests\Support\NonStrictConsumer;
use Maatify\Eligibility\Evaluation\Value\ContextValue;
use Maatify\Eligibility\Evaluation\Value\ContextValueCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalScalarBoundaryTest extends TestCase
{
    #[Test]
    public function contextContainsUsesExactCanonicalValues(): void
    {
        $values = new ContextValueCollection(new ContextValue('EG'));

        self::assertTrue(NonStrictConsumer::contains($values, 'EG'));
        self::assertFalse(NonStrictConsumer::contains($values, 'SA'));
    }

    #[Test]
    public function contextContainsRejectsNonStringValuesBeforeComparison(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::contains(
            new ContextValueCollection(new ContextValue('150')),
            150,
        );
    }

    #[Test]
    public function contextContainsRejectsPaddedValues(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::contains(
            new ContextValueCollection(new ContextValue('EG')),
            ' EG ',
        );
    }

    #[Test]
    public function contextContainsRejectsMalformedUtf8(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::contains(
            new ContextValueCollection(new ContextValue('EG')),
            "\xC3\x28",
        );
    }

    #[Test]
    public function canonicalOrderingRemainsBytewiseForValidStrings(): void
    {
        self::assertLessThan(0, NonStrictConsumer::compare('EG', 'SA'));
    }

    #[Test]
    public function canonicalOrderingRejectsNonStringOperands(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::compare(150, '200');
    }

    #[Test]
    public function canonicalOrderingRejectsPaddedOperands(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::compare(' EG ', 'SA');
    }

    #[Test]
    public function canonicalOrderingRejectsMalformedUtf8Operands(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        NonStrictConsumer::compare("\xC3\x28", 'SA');
    }

    #[Test]
    public function canonicalOrderingPreservesCaseAndUnicodeIdentity(): void
    {
        self::assertGreaterThan(0, NonStrictConsumer::compare('eg', 'EG'));
        self::assertNotSame(0, NonStrictConsumer::compare("é", "e\u{0301}"));
    }
}
