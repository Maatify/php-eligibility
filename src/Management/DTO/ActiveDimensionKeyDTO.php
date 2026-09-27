<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Management\DTO;

use JsonSerializable;
use Maatify\Eligibility\Common\CanonicalString;

/** One canonical active dimension key returned by paginated active-dimension discovery. */
final readonly class ActiveDimensionKeyDTO implements JsonSerializable
{
    public string $dimensionKey;

    /**
     * Validates the input against the canonical dimension-key contract;
     * invalid input raises InvalidEligibilityInputException.
     */
    public function __construct(mixed $dimensionKey)
    {
        $this->dimensionKey = CanonicalString::validateDimensionKey($dimensionKey);
    }

    /** @return array{dimensionKey: string} */
    public function jsonSerialize(): array
    {
        return [
            'dimensionKey' => $this->dimensionKey,
        ];
    }
}
