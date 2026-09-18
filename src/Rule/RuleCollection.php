<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Rule;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;
use Maatify\Eligibility\Common\Ordering\CanonicalOrdering;

/** @implements IteratorAggregate<int, Rule> */
final readonly class RuleCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Rule> */
    private array $items;

    public function __construct(Rule ...$rules)
    {
        $items = array_values($rules);
        foreach ($items as $index => $rule) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if ($items[$previousIndex]->naturalIdentity()->equals($rule->naturalIdentity())) {
                    throw new InvalidEligibilityInputException('Duplicate Rule natural identities are invalid.');
                }
            }
        }

        usort($items, static function (Rule $left, Rule $right): int {
            $leftIdentity = $left->naturalIdentity();
            $rightIdentity = $right->naturalIdentity();

            foreach ([
                [$leftIdentity->subjectType, $rightIdentity->subjectType],
                [$leftIdentity->subjectId, $rightIdentity->subjectId],
                [$leftIdentity->dimensionKey, $rightIdentity->dimensionKey],
                [$leftIdentity->dimensionValue, $rightIdentity->dimensionValue],
            ] as [$leftValue, $rightValue]) {
                $comparison = CanonicalOrdering::compareStrings($leftValue, $rightValue);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, Rule> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<Rule> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (Rule $rule): array => $rule->jsonSerialize(),
            $this->items,
        );
    }
}
