<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\ValueObject;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Common\CanonicalOrdering;

/**
 * Immutable set of zero or more context dimensions used for one evaluation.
 *
 * A dimension key may occur only once. An empty Context is valid and means no
 * dimensions were supplied; a present dimension must use a non-empty,
 * duplicate-free ContextValueCollection. Dimensions are retained in canonical
 * order so evaluation and serialization do not depend on caller or database order.
 *
 * @implements IteratorAggregate<int, ContextDimension>
 */
final readonly class Context implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<ContextDimension> */
    private array $dimensions;

    /** Rejects duplicate keys and stores supplied dimensions in canonical order; no dimensions is valid. */
    public function __construct(ContextDimension ...$dimensions)
    {
        $items = array_values($dimensions);
        foreach ($items as $index => $dimension) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->dimensionKey === $dimension->dimensionKey) {
                    throw new InvalidEligibilityInputException('Duplicate context dimensions are invalid.');
                }
            }
        }

        usort(
            $items,
            static fn(ContextDimension $left, ContextDimension $right): int => CanonicalOrdering::compareStrings(
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

    /** Returns null only when the validated key is absent; it never treats an empty dimension as absent. */
    public function getDimension(mixed $dimensionKey): ?ContextDimension
    {
        $canonicalKey = \Maatify\Eligibility\Common\CanonicalString::validateDimensionKey($dimensionKey);
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
            static fn(ContextDimension $dimension): array => $dimension->jsonSerialize(),
            $this->dimensions,
        );
    }
}
