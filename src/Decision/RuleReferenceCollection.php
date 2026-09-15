<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Ordering\CanonicalOrdering;
use Maatify\Eligibility\Rule\RuleEffectEnum;

/** @implements IteratorAggregate<int, RuleReference> */
final readonly class RuleReferenceCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<RuleReference> */
    private array $items;

    public function __construct(RuleReference ...$references)
    {
        $items = array_values($references);
        foreach ($items as $index => $reference) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->naturalIdentity()->equals($reference->naturalIdentity())) {
                    throw new InvalidArgumentException('Duplicate matched Rule references are invalid.');
                }
            }
        }

        usort($items, static function (RuleReference $left, RuleReference $right): int {
            $comparison = CanonicalOrdering::compareStrings($left->dimensionValue, $right->dimensionValue);
            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = CanonicalOrdering::compareStrings(
                $left->subject->subjectType,
                $right->subject->subjectType,
            );
            if ($comparison !== 0) {
                return $comparison;
            }

            $comparison = CanonicalOrdering::compareStrings($left->subject->subjectId, $right->subject->subjectId);
            if ($comparison !== 0) {
                return $comparison;
            }

            return CanonicalOrdering::compareStrings($left->dimensionKey, $right->dimensionKey);
        });

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, RuleReference> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function hasEffect(RuleEffectEnum $effect): bool
    {
        foreach ($this->items as $reference) {
            if ($reference->effect === $effect) {
                return true;
            }
        }

        return false;
    }

    /** @return list<RuleReference> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (RuleReference $reference): array => $reference->jsonSerialize(),
            $this->items,
        );
    }
}
