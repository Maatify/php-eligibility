<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Evaluation\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

/** @implements IteratorAggregate<int, SubjectDecisionDTO> */
final readonly class SubjectDecisionCollectionDTO implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<SubjectDecisionDTO> */
    private array $items;

    public function __construct(SubjectDecisionDTO ...$results)
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

    /** @return ArrayIterator<int, SubjectDecisionDTO> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<SubjectDecisionDTO> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<array{subject: \Maatify\Eligibility\ValueObject\Subject, decision: \Maatify\Eligibility\Evaluation\ValueObject\EligibilityDecision}> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn(SubjectDecisionDTO $result): array => $result->jsonSerialize(),
            $this->items,
        );
    }
}
