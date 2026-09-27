<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\DTO;

use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

/** Immutable Rule lifecycle count summary for one Subject/dimension scope. */
final readonly class RuleLifecycleSummaryDTO implements JsonSerializable
{
    /** Enforces that the counts are non-negative and internally consistent. */
    public function __construct(
        public int $totalRules,
        public int $activeRules,
        public int $inactiveRules,
    ) {
        if ($this->totalRules < 0 || $this->activeRules < 0 || $this->inactiveRules < 0) {
            throw new InvalidEligibilityInputException(
                'Rule lifecycle summary counts must not be negative.',
            );
        }

        if ($this->totalRules !== $this->activeRules + $this->inactiveRules) {
            throw new InvalidEligibilityInputException(
                'Rule lifecycle summary counts must be internally consistent.',
            );
        }
    }

    /** @return array{totalRules: int, activeRules: int, inactiveRules: int} */
    public function jsonSerialize(): array
    {
        return [
            'totalRules' => $this->totalRules,
            'activeRules' => $this->activeRules,
            'inactiveRules' => $this->inactiveRules,
        ];
    }
}
