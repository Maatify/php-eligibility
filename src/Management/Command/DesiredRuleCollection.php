<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\Command;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Common\Ordering\CanonicalOrdering;

/** @implements IteratorAggregate<int, DesiredRule> */
final readonly class DesiredRuleCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<DesiredRule> */
    private array $items;

    public function __construct(DesiredRule ...$desiredRules)
    {
        $items = array_values($desiredRules);

        foreach ($items as $index => $desiredRule) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->dimensionValue === $desiredRule->dimensionValue) {
                    throw new InvalidEligibilityInputException(
                        'Duplicate desired dimension values are invalid.',
                    );
                }
            }
        }

        usort(
            $items,
            static fn (DesiredRule $left, DesiredRule $right): int => CanonicalOrdering::compareStrings(
                $left->dimensionValue,
                $right->dimensionValue,
            ),
        );

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, DesiredRule> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<DesiredRule> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array{dimensionValue: string, effect: string}> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (DesiredRule $desiredRule): array => $desiredRule->jsonSerialize(),
            $this->items,
        );
    }
}
