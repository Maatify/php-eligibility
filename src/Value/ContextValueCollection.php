<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Value;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Ordering\CanonicalOrdering;
use Maatify\Eligibility\Validation\CanonicalString;

/** @implements IteratorAggregate<int, ContextValue> */
final readonly class ContextValueCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<ContextValue> */
    private array $items;

    public function __construct(ContextValue ...$values)
    {
        if ($values === []) {
            throw new InvalidEligibilityInputException('A present context dimension must contain at least one value.');
        }

        $items = array_values($values);
        foreach ($items as $index => $value) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->value === $value->value) {
                    throw new InvalidEligibilityInputException('Duplicate context dimension values are invalid.');
                }
            }
        }

        usort(
            $items,
            static fn (ContextValue $left, ContextValue $right): int => CanonicalOrdering::compareStrings(
                $left->value,
                $right->value,
            ),
        );

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, ContextValue> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function contains(mixed $value): bool
    {
        $canonicalValue = CanonicalString::validateDimensionValue($value, 'contextValue');

        foreach ($this->items as $item) {
            if ($item->value === $canonicalValue) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(
            static fn (ContextValue $value): string => $value->value,
            $this->items,
        );
    }

    /** @return list<string> */
    public function jsonSerialize(): array
    {
        return $this->values();
    }
}
