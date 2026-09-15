<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Tests\Unit;

use InvalidArgumentException;
use Maatify\Eligibility\Value\Context;
use Maatify\Eligibility\Value\ContextDimension;
use Maatify\Eligibility\Value\ContextValue;
use Maatify\Eligibility\Value\ContextValueCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    #[Test]
    public function anEmptyContextIsValid(): void
    {
        self::assertCount(0, new Context());
    }

    #[Test]
    public function contextValuesAreAUniqueCanonicalSet(): void
    {
        $context = new Context(ContextDimension::fromStrings('country', 'SA', 'EG'));

        self::assertSame(['EG', 'SA'], $context->getDimension('country')?->values->values());
        self::assertTrue($context->hasDimension('country'));
        self::assertFalse($context->hasDimension('customer_type'));
    }

    #[Test]
    public function duplicateDimensionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Context(
            ContextDimension::fromStrings('country', 'EG'),
            ContextDimension::fromStrings('country', 'SA'),
        );
    }

    #[Test]
    public function presentEmptyDimensionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContextDimension('country', new ContextValueCollection());
    }

    #[Test]
    public function duplicateValuesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContextValueCollection(new ContextValue('EG'), new ContextValue('EG'));
    }

    #[Test]
    public function dimensionsAreReturnedInCanonicalBytewiseOrder(): void
    {
        $context = new Context(
            ContextDimension::fromStrings('country', 'EG'),
            ContextDimension::fromStrings('customer_type', 'retail'),
        );

        $dimensions = iterator_to_array($context);

        self::assertSame('country', $dimensions[0]->dimensionKey);
        self::assertSame('customer_type', $dimensions[1]->dimensionKey);
    }
}
