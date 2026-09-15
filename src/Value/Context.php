<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Value;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Ordering\CanonicalOrdering;

/** @implements IteratorAggregate<int, ContextDimension> */
final readonly class Context implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<ContextDimension> */
    private array $dimensions;

    public function __construct(ContextDimension ...$dimensions)
    {
        $items = array_values($dimensions);
        foreach ($items as $index => $dimension) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->dimensionKey === $dimension->dimensionKey) {
                    throw new InvalidArgumentException('Duplicate context dimensions are invalid.');
                }
            }
        }

        usort(
            $items,
            static fn (ContextDimension $left, ContextDimension $right): int => CanonicalOrdering::compareStrings(
                $left->dimensionKey,
                $right->dimensionKey,
            ),
        );

        $this->dimensions = $items;
    }

    public function count(): int
    {
        return count($this->dimensions);
    }

    /** @return ArrayIterator<int, ContextDimension> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->dimensions);
    }

    public function getDimension(mixed $dimensionKey): ?ContextDimension
    {
        $canonicalKey = \Maatify\Eligibility\Validation\CanonicalString::validate($dimensionKey, 'dimensionKey');
        foreach ($this->dimensions as $dimension) {
            if ($dimension->dimensionKey === $canonicalKey) {
                return $dimension;
            }
        }

        return null;
    }

    public function hasDimension(mixed $dimensionKey): bool
    {
        return $this->getDimension($dimensionKey) !== null;
    }

    /** @return list<array{dimensionKey: string, values: list<string>}> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (ContextDimension $dimension): array => $dimension->jsonSerialize(),
            $this->dimensions,
        );
    }
}
