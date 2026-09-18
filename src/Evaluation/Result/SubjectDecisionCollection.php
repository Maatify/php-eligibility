<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\Result;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

/** @implements IteratorAggregate<int, SubjectDecisionResult> */
final readonly class SubjectDecisionCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<SubjectDecisionResult> */
    private array $items;

    public function __construct(SubjectDecisionResult ...$results)
    {
        $items = array_values($results);

        foreach ($items as $index => $result) {
            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                if (
                    $items[$previousIndex]->subject->subjectType === $result->subject->subjectType
                    && $items[$previousIndex]->subject->subjectId === $result->subject->subjectId
                ) {
                    throw new InvalidEligibilityInputException(
                        'Duplicate Subject decision results are invalid.',
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

    /** @return ArrayIterator<int, SubjectDecisionResult> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<SubjectDecisionResult> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array{subject: \Maatify\Eligibility\Common\Value\Subject, decision: \Maatify\Eligibility\Evaluation\Decision\EligibilityDecision}> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn (SubjectDecisionResult $result): array => $result->jsonSerialize(),
            $this->items,
        );
    }
}
