<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Result;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Common\Ordering\CanonicalOrdering;
use Maatify\Eligibility\Common\Validation\CanonicalString;

/** @implements IteratorAggregate<int, string> */
final readonly class ActiveDimensionKeyCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<string> */
    private array $items;

    public function __construct(mixed ...$dimensionKeys)
    {
        $items = array_map(
            static fn (mixed $dimensionKey): string => CanonicalString::validateDimensionKey($dimensionKey),
            $dimensionKeys,
        );

        foreach ($items as $index => $dimensionKey) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex] === $dimensionKey) {
                    throw new InvalidEligibilityInputException(
                        'Duplicate active dimension keys are invalid.',
                    );
                }
            }
        }

        usort($items, static fn (string $left, string $right): int => CanonicalOrdering::compareStrings($left, $right));

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, string> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function contains(mixed $dimensionKey): bool
    {
        $canonicalKey = CanonicalString::validateDimensionKey($dimensionKey);

        return in_array($canonicalKey, $this->items, true);
    }

    /** @return list<string> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<string> */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
