<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Decision;

use JsonSerializable;
use Maatify\Eligibility\Exception\InvalidEligibilityInputException;

final readonly class EligibilityDecision implements JsonSerializable
{
    public function __construct(
        mixed $eligible,
        public DecisionReasonEnum $reasonCode,
        public DimensionOutcomeCollection $dimensionOutcomes,
    ) {
        if (!is_bool($eligible)) {
            throw new InvalidEligibilityInputException('Decision eligible state must be a boolean.');
        }

        if ($reasonCode === DecisionReasonEnum::UNRESTRICTED) {
            if (!$eligible || $dimensionOutcomes->count() !== 0) {
                throw new InvalidEligibilityInputException('UNRESTRICTED requires eligible=true and no dimension outcomes.');
            }
        }

        if ($reasonCode === DecisionReasonEnum::ELIGIBLE) {
            if (!$eligible || $dimensionOutcomes->count() === 0 || !$dimensionOutcomes->allPassed()) {
                throw new InvalidEligibilityInputException('ELIGIBLE requires non-empty all-passing dimension outcomes.');
            }
        }

        if ($reasonCode === DecisionReasonEnum::DENIED) {
            if ($eligible || $dimensionOutcomes->count() === 0 || !$dimensionOutcomes->hasFailed()) {
                throw new InvalidEligibilityInputException('DENIED requires non-empty outcomes with at least one failure.');
            }
        }

        $this->eligible = $eligible;
    }

    public bool $eligible;

    public static function unrestricted(): self
    {
        return new self(true, DecisionReasonEnum::UNRESTRICTED, new DimensionOutcomeCollection());
    }

    public static function eligible(DimensionOutcomeCollection $dimensionOutcomes): self
    {
        return new self(true, DecisionReasonEnum::ELIGIBLE, $dimensionOutcomes);
    }

    public static function denied(DimensionOutcomeCollection $dimensionOutcomes): self
    {
        return new self(false, DecisionReasonEnum::DENIED, $dimensionOutcomes);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'eligible' => $this->eligible,
            'reasonCode' => $this->reasonCode->value,
            'dimensionOutcomes' => $this->dimensionOutcomes,
        ];
    }
}
