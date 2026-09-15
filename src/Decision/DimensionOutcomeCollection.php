<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Ordering\CanonicalOrdering;

/** @implements IteratorAggregate<int, DimensionOutcome> */
final readonly class DimensionOutcomeCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<DimensionOutcome> */
    private array $items;

    public function __construct(DimensionOutcome ...$outcomes)
    {
        $items = array_values($outcomes);
        foreach ($items as $index => $outcome) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->dimensionKey === $outcome->dimensionKey) {
                    throw new InvalidEligibilityInputException('Duplicate dimension outcomes are invalid.');
                }
            }
        }

        usort(
            $items,
            static fn (DimensionOutcome $left, DimensionOutcome $right): int => CanonicalOrdering::compareStrings(
                $left->dimensionKey,
                $right->dimensionKey,
            ),
        );

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, DimensionOutcome> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function allPassed(): bool
    {
        foreach ($this->items as $outcome) {
            if (!$outcome->passed) {
                return false;
            }
        }

        return true;
    }

    public function hasFailed(): bool
    {
        foreach ($this->items as $outcome) {
            if (!$outcome->passed) {
                return true;
            }
        }

        return false;
    }

    /** @return list<DimensionOutcome> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (DimensionOutcome $outcome): array => $outcome->jsonSerialize(),
            $this->items,
        );
    }
}
