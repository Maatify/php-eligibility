<?php

declare(strict_types=1);

namespace Maatify\Eligibility\Value;

use JsonSerializable;
use Maatify\Eligibility\Validation\CanonicalString;

final readonly class Subject implements JsonSerializable
{
    public string $subjectType;

    public string $subjectId;

    public function __construct(mixed $subjectType, mixed $subjectId)
    {
        $this->subjectType = CanonicalString::validate($subjectType, 'subjectType');
        $this->subjectId = CanonicalString::validate($subjectId, 'subjectId');
    }

    /** @return array{subjectType: string, subjectId: string} */
    public function jsonSerialize(): array
    {
        return [
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
        ];
    }
}
