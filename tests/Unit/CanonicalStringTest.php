<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Value\Subject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalStringTest extends TestCase
{
    #[Test]
    public function itPreservesAValidUtf8StringExactly(): void
    {
        $subject = new Subject('product', 'été / 150');

        self::assertSame('été / 150', $subject->subjectId);
    }

    #[Test]
    public function itRejectsNonStringValuesInsteadOfCoercingThem(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new Subject('product', 150);
    }

    #[Test]
    public function itRejectsMalformedUtf8(): void
    {
        $this->expectException(InvalidEligibilityInputException::class);

        new Subject('product', "\xC3\x28");
    }

    #[Test]
    public function itRejectsEmptyWhitespaceOnlyAndPaddedValues(): void
    {
        foreach (['', ' ', "\tEG", "EG\n", "\u{00A0}EG", "EG\u{00A0}"] as $value) {
            try {
                new Subject('product', $value);
                self::fail('Expected invalid canonical string: ' . bin2hex($value));
            } catch (InvalidEligibilityInputException) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function itDoesNotNormalizeCaseOrUnicode(): void
    {
        $lower = new Subject('product', 'eg');
        $composed = new Subject('product', "é");
        $decomposed = new Subject('product', "e\u{0301}");

        self::assertNotSame('EG', $lower->subjectId);
        self::assertNotSame($composed->subjectId, $decomposed->subjectId);
    }
}
