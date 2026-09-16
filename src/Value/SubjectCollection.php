<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Value;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

/** @implements IteratorAggregate<int, Subject> */
final readonly class SubjectCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Subject> */
    private array $items;

    public function __construct(Subject ...$subjects)
    {
        $items = array_values($subjects);

        foreach ($items as $index => $subject) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if (
                    $items[$previousIndex]->subjectType === $subject->subjectType
                    && $items[$previousIndex]->subjectId === $subject->subjectId
                ) {
                    throw new InvalidEligibilityInputException(
                        'Duplicate Subject identities are invalid in a batch.',
                    );
                }
            }
        }

        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return ArrayIterator<int, Subject> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<Subject> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array{subjectType: string, subjectId: string}> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (Subject $subject): array => $subject->jsonSerialize(),
            $this->items,
        );
    }
}
